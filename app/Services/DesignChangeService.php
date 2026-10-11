<?php

namespace App\Services;

use App\Models\{Order, User};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class DesignChangeService
{
    public function __construct(private DesignChangeStore $store) {}

    public function eligible(Order $order): bool
    {
        return $order->user_id === null && in_array($order->status, ['pending', 'confirmed', 'preparing'], true);
    }

    public function snapshot(Order $order): array
    {
        return $order->orderDetails()->with('images')->orderBy('id')->get()->map(fn ($line) => [
            'id' => $line->id, 'name' => $line->product_name_snapshot ?? $line->product?->product_name ?? 'Package',
            'themes' => $line->themes ?? '', 'special_request' => $line->special_request ?? '',
            'references' => $line->images->sortBy('id')->map(fn ($image) => [
                'key' => 'image-'.$image->id, 'id' => $image->id, 'file_path' => $image->file_path,
                'disk' => $this->imageDisk($image->file_path), 'name' => $image->original_filename,
            ])->values()->all(),
        ])->all();
    }

    /** Semantic fingerprint, independent of generated image IDs and payment state. */
    public function fingerprint(array $snapshot): string
    {
        $normal = array_map(function ($line) {
            $images = array_map(fn ($image) => [$image['disk'], $image['file_path'], $image['name']], $line['references']);
            sort($images);
            return [(int) $line['id'], $line['themes'], $line['special_request'], $images];
        }, $snapshot);
        usort($normal, fn ($a, $b) => $a[0] <=> $b[0]);
        return hash('sha256', json_encode($normal, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function summary(Order $order): array
    {
        try {
            $state = $this->store->read($order);
            if (collect($state['requests'])->contains('status', 'applying')) {
                $this->recover($order);
                $state = $this->store->read($order, true);
            }
            if (! $this->eligible($order)) {
                foreach ($state['requests'] as &$change) {
                    if (in_array($change['status'], ['pending', 'applying'], true)) {
                        $change['status'] = 'closed';
                        $change['reason'] = 'This order is '.$order->status.'.';
                    }
                }
                unset($change);
            }
            $history = array_reverse(array_values($state['requests']));
            $pending = collect($history)->firstWhere('status', 'pending');
            return ['available' => true, 'hold' => (bool) $pending, 'pending' => $pending,
                'latest' => $history[0] ?? null, 'history' => $history,
                'photos_expired_at' => $state['retention']['photos_expired_at'] ?? null,
                'history_expired_at' => $state['retention']['history_expired_at'] ?? null,
                'retention_hold' => ! empty($state['retention']['hold']),
                'revision' => hash('sha256', json_encode([$order->status, $state['requests'],
                    $state['retention']['photos_expired_at'] ?? null,
                    $state['retention']['history_expired_at'] ?? null, $state['retention']['hold'] ?? null]))];
        } catch (\Throwable $error) {
            report($error);
            return ['available' => false, 'hold' => $this->eligible($order), 'pending' => null,
                'latest' => null, 'history' => [], 'photos_expired_at' => null, 'history_expired_at' => null,
                'retention_hold' => false, 'revision' => 'unavailable'];
        }
    }

    public function assertNoPending(Order $order): void
    {
        try {
            $state = $this->store->read($order, true);
        } catch (\Throwable $error) {
            report($error);
            throw ValidationException::withMessages(['design_change' => 'Design change records are unavailable. Preparation stays paused until the records are restored.']);
        }
        if (collect($state['requests'])->contains(fn ($change) => in_array($change['status'], ['pending', 'applying'], true))) {
            throw ValidationException::withMessages(['design_change' => 'Preparation is paused. Resolve or withdraw the pending design change first.']);
        }
    }

    public function liveRevision(Order $order, ?array $summary = null): string
    {
        $order->loadMissing(['payments', 'paymentProofs']);
        return hash('sha256', json_encode([
            ($summary ?? $this->summary($order))['revision'], $order->updated_at?->toIso8601String(),
            $order->workflowLabel(), $order->amount_paid,
            $order->paymentProofs->map(fn ($proof) => [$proof->id, $proof->status, $proof->updated_at?->toIso8601String()])->all(),
        ]));
    }

    public function submit(Order $order, array $draft, array $items): array
    {
        return DB::transaction(function () use ($order, $draft, $items) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            return $this->store->locked($order, function ($state) use ($order, $draft, $items) {
                if (isset($state['requests'][$draft['id']])) return $state['requests'][$draft['id']];
                $this->requireEligible($order);
                $this->assertNoPending($order);
                $before = $this->snapshot($order);
                if ($this->fingerprint($before) !== $draft['fingerprint']) {
                    throw ValidationException::withMessages(['design_change' => 'The saved design changed. Review the latest order before submitting again.']);
                }
                $proposed = $before;
                foreach ($proposed as &$line) {
                    $item = $items[$line['id']];
                    $line['themes'] = trim($item['themes'] ?? '');
                    $line['special_request'] = trim($item['special_request'] ?? '');
                    $keep = array_map('intval', $item['keep_images'] ?? []);
                    if (array_diff($keep, array_column($line['references'], 'id'))) {
                        throw ValidationException::withMessages(['design_change' => 'Choose reference photos belonging to this package.']);
                    }
                    $line['references'] = array_values(array_filter($line['references'], fn ($image) => in_array($image['id'], $keep, true)));
                    foreach (array_unique($item['staged_images'] ?? []) as $key) {
                        $image = $draft['uploads'][$key] ?? null;
                        if (! $image || $image['line_id'] !== $line['id'] || ! Storage::disk('design_references')->exists($image['file_path'])) {
                            throw ValidationException::withMessages(['design_change' => 'Upload a reference photo for this package again.']);
                        }
                        $line['references'][] = ['key' => $key, 'id' => null, 'file_path' => $image['file_path'], 'disk' => 'design_references', 'name' => $image['name']];
                    }
                    if (count($line['references']) > 5) throw ValidationException::withMessages(['design_change' => 'Use up to five reference photos per package, including retained photos.']);
                }
                unset($line);
                if ($this->fingerprint($before) === $this->fingerprint($proposed)) {
                    throw ValidationException::withMessages(['design_change' => 'Change a theme, instruction, or reference photo before submitting.']);
                }
                $change = ['id' => $draft['id'], 'status' => 'pending', 'submitted_at' => now()->toIso8601String(),
                    'before' => $before, 'proposed' => $proposed, 'base_fingerprint' => $draft['fingerprint'],
                    'reviewer_id' => null, 'reviewer_name' => null, 'decided_at' => null, 'reason' => null];
                $state['requests'][$change['id']] = $change;
                $this->store->write($order, $state);
                return $change;
            });
        });
    }

    public function review(Order $order, string $id, string $decision, User $user, ?string $reason = null): array
    {
        $user = StaffAccess::require($user);
        if (! in_array($decision, ['approved', 'declined'], true) || ($decision === 'declined' && trim($reason ?? '') === '')) {
            throw ValidationException::withMessages(['reason' => 'Choose a valid decision and explain any declined change.']);
        }
        $this->recover($order);
        try {
            DB::transaction(function () use ($order, $id, $decision, $user, $reason) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->store->locked($order, function ($state) use ($order, $id, $decision, $user, $reason) {
                    if (collect($state['requests'])->contains('status', 'applying')) {
                        $state = $this->settleApplying($order, $state);
                        $this->store->write($order, $state);
                    }
                    $change = $state['requests'][$id] ?? null;
                    abort_unless($change, 404);
                    if ($change['status'] === $decision) return;
                    $this->requireEligible($order);
                    if ($change['status'] !== 'pending') throw ValidationException::withMessages(['design_change' => 'This request already has a decision. Reload the order.']);
                    $change['reviewer_id'] = $user->id;
                    $change['reviewer_name'] = $user->full_name;
                    $change['decided_at'] = now()->toIso8601String();
                    $change['reason'] = $reason;
                    if ($decision === 'declined') {
                        $change['status'] = 'declined';
                        $state['requests'][$id] = $change;
                        $this->store->write($order, $state);
                        return;
                    }
                    if ($this->fingerprint($this->snapshot($order)) !== $change['base_fingerprint']) {
                        throw ValidationException::withMessages(['design_change' => 'The current design differs from this request. Decline it with an explanation and ask the customer to submit a fresh change.']);
                    }
                    foreach ($change['proposed'] as $line) {
                        foreach ($line['references'] as $image) {
                            if (! Storage::disk($image['disk'])->exists($image['file_path'])) {
                                throw ValidationException::withMessages(['design_change' => 'A reference file is missing. Restore it before approving this request.']);
                            }
                        }
                    }
                    // Durable intent precedes the DB commit. Recovery compares semantic
                    // before/after fingerprints, so a crash cannot apply a change twice.
                    $change['status'] = 'applying';
                    $state['requests'][$id] = $change;
                    $this->store->write($order, $state);
                    foreach ($change['proposed'] as $line) {
                        $detail = $order->orderDetails()->whereKey($line['id'])->firstOrFail();
                        $detail->update(['themes' => $line['themes'] ?: null, 'special_request' => $line['special_request'] ?: null]);
                        $keep = array_filter(array_column($line['references'], 'id'));
                        // Retain original files and metadata in the immutable before snapshot.
                        $detail->images()->whereNotIn('id', $keep)->delete();
                        foreach ($line['references'] as $image) {
                            if ($image['id'] !== null) continue;
                            $detail->images()->create(['order_id' => $order->id, 'file_path' => $image['file_path'],
                                'original_filename' => $image['name'], 'uploaded_by' => null]);
                        }
                    }
                });
            });
        } finally {
            $this->recover($order);
        }
        return $this->find($order, $id);
    }

    public function withdraw(Order $order, string $id): void
    {
        $this->recover($order);
        DB::transaction(function () use ($order, $id) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->store->locked($order, function ($state) use ($order, $id) {
                if (collect($state['requests'])->contains('status', 'applying')) {
                    $state = $this->settleApplying($order, $state);
                    $this->store->write($order, $state);
                }
                $change = $state['requests'][$id] ?? null;
                abort_unless($change, 404);
                if ($change['status'] === 'withdrawn') return;
                $this->requireEligible($order);
                if ($change['status'] !== 'pending') throw ValidationException::withMessages(['design_change' => 'This request already has a decision.']);
                $change['status'] = 'withdrawn';
                $change['decided_at'] = now()->toIso8601String();
                $change['reason'] = 'Withdrawn by the customer. The current design remains in effect.';
                $state['requests'][$id] = $change;
                $this->store->write($order, $state);
            });
        });
    }

    public function close(Order $order): void
    {
        if ($this->eligible($order) || ! file_exists($this->store->statePath($order))) return;
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($this->eligible($order)) return;
            $this->store->locked($order, function ($state) use ($order) {
                foreach ($state['requests'] as &$change) {
                    if (! in_array($change['status'], ['pending', 'applying'], true)) continue;
                    $change['status'] = 'closed';
                    $change['reason'] = 'This order is '.$order->status.'.';
                    $change['decided_at'] = ($order->cancelled_at ?? $order->ready_at ?? now())->toIso8601String();
                }
                unset($change);
                $this->store->write($order, $state);
            });
        });
    }

    public function recover(Order $order): void
    {
        $state = $this->store->read($order, true);
        if (! collect($state['requests'])->contains('status', 'applying')) return;
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->store->locked($order, function ($state) use ($order) {
                $this->store->write($order, $this->settleApplying($order, $state));
            });
        });
    }

    /** Caller holds both the order row and file locks, so commits are observable. */
    private function settleApplying(Order $order, array $state): array
    {
        $actual = $this->fingerprint($this->snapshot($order));
        foreach ($state['requests'] as &$change) {
            if ($change['status'] !== 'applying') continue;
            if ($actual === $this->fingerprint($change['proposed'])) {
                $change['status'] = 'approved';
            } else {
                $change['status'] = 'pending';
                $change['reviewer_id'] = $change['reviewer_name'] = $change['decided_at'] = null;
                $change['reason'] = $actual === $change['base_fingerprint'] ? null : 'The saved design changed during recovery. Review it before continuing.';
            }
        }
        unset($change);
        return $state;
    }

    public function find(Order $order, string $id): array
    {
        $state = $this->store->read($order, true);
        abort_unless(isset($state['requests'][$id]), 404);
        return $state['requests'][$id];
    }

    public function reference(Order $order, string $id, string $key): array
    {
        app(CompletedOrderRetentionService::class)->assertPhotosAvailable($order);
        $change = $this->find($order, $id);
        foreach (array_merge($change['before'], $change['proposed']) as $line) {
            foreach ($line['references'] as $image) if ($image['key'] === $key) return $image;
        }
        abort(404);
    }

    public function imageDisk(string $path): string
    {
        return str_starts_with($path, 'design-changes/') ? 'design_references'
            : (str_starts_with($path, 'staff/') ? 'staff_references' : 'public');
    }

    private function requireEligible(Order $order): void
    {
        if (! $this->eligible($order)) throw ValidationException::withMessages(['design_change' => 'Design changes are available only before the order is ready for pickup.']);
    }
}
