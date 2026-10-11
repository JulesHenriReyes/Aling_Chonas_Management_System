<?php

namespace App\Services;

use App\Models\{AddOn, Order, OrderImage, Product};
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;

class CompletedOrderRetentionService
{
    public function __construct(private DesignChangeStore $store) {}

    public function run(bool $apply = false): array
    {
        $result = ['apply' => $apply, 'orders_checked' => 0, 'photos_expired' => 0,
            'histories_expired' => 0, 'files' => 0, 'bytes' => 0, 'protected_files' => 0,
            'skipped_orders' => 0, 'errors' => [], 'already_running' => false];
        $root = $this->store->root();
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Cannot open the order retention storage.');
        }
        $lock = fopen($root.'/retention.lock', 'c+b');
        if (! $lock) throw new RuntimeException('Cannot open the order retention lock.');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $result['already_running'] = true;
            return $result;
        }

        try {
            $photoDays = (int) config('order-retention.reference_photo_days');
            $historyDays = (int) config('order-retention.design_history_days');
            if ($photoDays < 1 || $historyDays < $photoDays) {
                throw new RuntimeException('Invalid order retention configuration.');
            }
            // One instant for every cutoff in this run; never use updated_at.
            $instant = now();
            $photoCutoff = $instant->copy()->subDays($photoDays);
            $historyCutoff = $instant->copy()->subDays($historyDays);
            // An unreadable history may contain a reference to another order's file.
            // Abort deletion rather than assume that file is unowned.
            $protected = $this->protectedHistoryReferences();
            foreach (Order::where('status', 'completed')->whereNotNull('completed_at')
                ->where('completed_at', '<=', $photoCutoff)->lazyById(100) as $order) {
                $result['orders_checked']++;
                try {
                    $this->process($order, $photoCutoff, $historyCutoff, $instant, $protected, $apply, $result);
                } catch (\Throwable $error) {
                    $result['skipped_orders']++;
                    $result['errors'][] = $order->order_number.': '.$error->getMessage();
                    report($error);
                } finally {
                    $this->store->forget($order);
                }
            }
        } catch (\Throwable $error) {
            $result['errors'][] = $error->getMessage();
            report($error);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $result;
    }

    public function setHold(Order $order, ?string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->store->locked($order, function ($state) use ($order, $reason) {
                if ($reason !== null && ! empty($state['retention']['photos_expired_at'])) {
                    throw new RuntimeException('Photos have already expired. A hold cannot restore deleted files.');
                }
                $state['retention']['hold'] = $reason === null ? null
                    : ['reason' => $reason, 'placed_at' => now()->toIso8601String()];
                $this->store->write($order, $state);
            });
        });
    }

    public function assertPhotosAvailable(Order $order): void
    {
        $state = $this->store->read($order);
        abort_if(! empty($state['retention']['photos_expired_at']), 410,
            'Reference photos expired after the completed-order retention period.');
    }

    private function process(Order $order, CarbonInterface $photoCutoff, CarbonInterface $historyCutoff,
        CarbonInterface $instant, array &$protected, bool $apply, array &$result): void
    {
        DB::transaction(function () use ($order, $photoCutoff, $historyCutoff, $instant, &$protected, $apply, &$result) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            // Recheck after locking: a queued job must never sweep a live/cancelled order.
            if ($order->status !== 'completed' || ! $order->completed_at
                || $order->completed_at->greaterThan($photoCutoff)) return;
            $identity = $this->store->identity($order);
            if (! file_exists($this->store->statePath($order)) && ! $order->images()->exists()
                && ! Storage::disk('design_references')->directoryExists('design-changes/'.$identity)) return;
            $work = function ($state) use ($order, $identity, $historyCutoff, $instant, &$protected, $apply, &$result) {
                if (! empty($state['retention']['hold'])
                    || collect($state['requests'])->contains(fn ($change) => in_array($change['status'], ['pending', 'applying'], true))) {
                    $result['skipped_orders']++;
                    return;
                }
                $expireHistory = $order->completed_at->lessThanOrEqualTo($historyCutoff)
                    && $state['requests'] && empty($state['retention']['history_expired_at']);
                $alreadyExpired = ! empty($state['retention']['photos_expired_at']);
                if ($alreadyExpired && empty($state['retention']['pending_deletions']) && ! $expireHistory) return;
                $candidates = $alreadyExpired
                    ? $this->keyedFiles($state['retention']['pending_deletions'] ?? [])
                    : $this->candidates($order, $state);
                foreach ($candidates as $key => $file) {
                    // A shared image may belong to another receipt's namespace.
                    // Its original owner handles physical deletion when safe.
                    if ($this->foreignReference($file, $order)) {
                        unset($candidates[$key]);
                        $result['protected_files']++;
                    }
                }
                // Check every destination before recording an irreversible expiration.
                foreach ($candidates as $file) $this->absoluteFile($file['disk'], $file['file_path'], $order);
                $expirePhotos = empty($state['retention']['photos_expired_at'])
                    && ($candidates || $order->images()->exists());
                if ($expirePhotos) {
                    $state['retention']['photos_expired_at'] = $instant->toIso8601String();
                    $result['photos_expired']++;
                }
                if ($expireHistory) {
                    $state['requests'] = [];
                    $state['retention']['history_expired_at'] = $instant->toIso8601String();
                    $result['histories_expired']++;
                }
                $beforeJournal = $state['retention']['pending_deletions'] ?? [];
                $state['retention']['pending_deletions'] = array_values($candidates);
                if ($apply && ($expirePhotos || $expireHistory || $beforeJournal !== array_values($candidates))) {
                    // Persist the expiration and retry list before deleting any bytes.
                    $this->store->write($order, $state);
                }
                if (! empty($state['retention']['photos_expired_at'])) {
                    foreach ($protected as &$owners) unset($owners[$identity]);
                    unset($owners);
                }
                $remaining = [];
                foreach ($candidates as $file) {
                    $key = $file['disk'].':'.$file['file_path'];
                    if ($this->referencedElsewhere($order, $file, $protected[$key] ?? [])) {
                        $result['protected_files']++;
                        $remaining[] = $file;
                        continue;
                    }
                    try {
                        $absolute = $this->absoluteFile($file['disk'], $file['file_path'], $order);
                        if ($absolute === null) continue; // Already deleted by an interrupted run.
                        $size = filesize($absolute);
                        if ($size === false) throw new RuntimeException('Cannot measure a reference photo.');
                        if ($apply && ! $this->deleteFile($absolute)) {
                            throw new RuntimeException('Cannot delete a reference photo. Cleanup will retry.');
                        }
                        $result['files']++;
                        $result['bytes'] += $size;
                        if ($apply) $this->removeEmptyDirectories($absolute, $file['disk'], $order);
                    } catch (\Throwable $error) {
                        $remaining[] = $file;
                        $result['errors'][] = $order->order_number.': '.$error->getMessage();
                        report($error);
                    }
                }
                if ($apply && $state['retention']['pending_deletions'] !== $remaining) {
                    $state['retention']['pending_deletions'] = $remaining;
                    $this->store->write($order, $state);
                }
            };
            if ($apply) $this->store->locked($order, $work);
            else $work($this->store->read($order, true));
        });
    }

    /** Only exact recorded files and this receipt identity's design uploads. */
    private function candidates(Order $order, array $state): array
    {
        $files = [];
        $add = function (string $disk, string $path) use (&$files) {
            $files[$disk.':'.$path] = ['disk' => $disk, 'file_path' => $path];
        };
        foreach ($order->images()->get(['file_path']) as $image) {
            $add(app(DesignChangeService::class)->imageDisk($image->file_path), $image->file_path);
        }
        foreach ($state['requests'] as $change) {
            foreach (array_merge($change['before'], $change['proposed']) as $line) {
                foreach ($line['references'] as $image) $add($image['disk'], $image['file_path']);
            }
        }
        foreach ($state['retention']['pending_deletions'] ?? [] as $file) $add($file['disk'], $file['file_path']);
        foreach (Storage::disk('design_references')->allFiles('design-changes/'.$this->store->identity($order)) as $path) {
            $add('design_references', $path);
        }
        return $files;
    }

    private function keyedFiles(array $files): array
    {
        return collect($files)->keyBy(fn ($file) => $file['disk'].':'.$file['file_path'])->all();
    }

    private function foreignReference(array $file, Order $order): bool
    {
        $path = $file['file_path'];
        if (in_array($file['disk'], ['public', 'staff_references'], true)
            && preg_match('~^(order_images|staff)/([1-9][0-9]*)/~', $path, $match)) {
            return (int) $match[2] !== (int) $order->id;
        }
        if ($file['disk'] === 'design_references' && preg_match('~^design-changes/([a-f0-9]{64})/~', $path, $match)) {
            return $match[1] !== $this->store->identity($order);
        }
        return false;
    }

    private function protectedHistoryReferences(): array
    {
        $protected = [];
        foreach (glob($this->store->root().'/orders/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $path = $directory.'/state.json';
            if (! file_exists($path)) {
                if (file_exists($directory.'/registered')) {
                    throw new RuntimeException('Registered design history is missing. Restore it before cleanup.');
                }
                continue;
            }
            $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($state['format'] ?? null) !== 1 || ($state['identity'] ?? null) !== basename($directory)
                || ! is_int($state['order_id'] ?? null) || ! is_array($state['requests'] ?? null)) {
                throw new RuntimeException('Invalid design history. Restore it before cleanup.');
            }
            $order = Order::find($state['order_id']);
            $matches = $order && $this->store->identity($order) === $state['identity'];
            if ($matches) {
                $state = $this->store->read($order, true);
                $this->store->forget($order);
            }
            // Unknown/recycled identities remain protected; never infer their age.
            if ($matches && $order->status === 'completed' && ! empty($state['retention']['photos_expired_at'])
                && empty($state['retention']['hold'])) continue;
            foreach ($state['requests'] as $change) {
                if (! is_array($change['before'] ?? null) || ! is_array($change['proposed'] ?? null)) {
                    throw new RuntimeException('Invalid reference history. Restore it before cleanup.');
                }
                foreach (array_merge($change['before'], $change['proposed']) as $line) {
                    if (! is_array($line['references'] ?? null)) throw new RuntimeException('Invalid reference history.');
                    foreach ($line['references'] as $image) {
                        if (! is_string($image['disk'] ?? null) || ! is_string($image['file_path'] ?? null)) {
                            throw new RuntimeException('Invalid reference history.');
                        }
                        $protected[$image['disk'].':'.$image['file_path']][$state['identity']] = true;
                    }
                }
            }
        }
        return $protected;
    }

    private function referencedElsewhere(Order $order, array $file, array $historyOwners): bool
    {
        unset($historyOwners[$this->store->identity($order)]);
        if ($historyOwners) return true;
        if ($file['disk'] === 'public' && (Product::where('photo_path', $file['file_path'])->exists()
            || AddOn::where('photo_path', $file['file_path'])->exists())) return true;
        foreach (OrderImage::where('file_path', $file['file_path'])->where('order_id', '!=', $order->id)
            ->with('order')->get() as $image) {
            $other = $image->order;
            if (! $other || $other->status !== 'completed') return true;
            $state = $this->store->read($other, true);
            $this->store->forget($other);
            if (empty($state['retention']['photos_expired_at']) || ! empty($state['retention']['hold'])) return true;
        }
        return false;
    }

    /** Resolve a local file without following a link or leaving its upload root. */
    private function absoluteFile(string $disk, string $path, Order $order): ?string
    {
        $prefix = match ($disk) {
            'public' => 'order_images/'.$order->id,
            'staff_references' => 'staff/'.$order->id,
            'design_references' => 'design-changes/'.$this->store->identity($order).'/[a-f0-9-]{36}',
            default => throw new RuntimeException('Cleanup refuses a non-reference storage disk.'),
        };
        if (! preg_match('~^'.$prefix.'/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpg|jpeg|png|webp)$~iD', $path)
            || str_contains($path, '..') || config('filesystems.disks.'.$disk.'.driver') !== 'local') {
            throw new RuntimeException('Cleanup refuses a path outside the reference-upload directories.');
        }
        $configuredRoot = Storage::disk($disk)->path('');
        $root = realpath($configuredRoot);
        if ($root === false) {
            if (file_exists($configuredRoot)) throw new RuntimeException('Cannot resolve a reference-upload directory.');
            return null;
        }
        $expected = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        // Links/junctions inside the root may point to another upload or business file.
        $part = $root;
        foreach (explode('/', $path) as $segment) {
            $part .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($part)) throw new RuntimeException('Cleanup refuses a linked reference path.');
            $resolved = realpath($part);
            if ($resolved !== false && $this->normalized($resolved) !== $this->normalized($part)) {
                throw new RuntimeException('Cleanup refuses a redirected reference path.');
            }
        }
        if (! file_exists($expected)) return null;
        $actual = realpath($expected);
        if (! $actual || ! is_file($actual)
            || $this->normalized($actual) !== $this->normalized($expected)
            || ! str_starts_with($this->normalized($actual), $this->normalized($root).'/')) {
            throw new RuntimeException('Cleanup refuses an unsafe reference path.');
        }
        return $actual;
    }

    private function normalized(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    private function removeEmptyDirectories(string $absolute, string $disk, Order $order): void
    {
        $root = realpath(Storage::disk($disk)->path(''));
        $namespace = match ($disk) {
            'public' => 'order_images/'.$order->id,
            'staff_references' => 'staff/'.$order->id,
            'design_references' => 'design-changes/'.$this->store->identity($order),
        };
        $floor = $this->normalized($root.'/'.$namespace);
        for ($directory = dirname($absolute); ; $directory = dirname($directory)) {
            $normalized = $this->normalized($directory);
            if ($normalized !== $floor && ! str_starts_with($normalized, $floor.'/')) return;
            if (is_link($directory) || $this->normalized(realpath($directory) ?: '') !== $normalized) return;
            $contents = scandir($directory);
            // rmdir is deliberately non-recursive. Any remaining file stops cleanup.
            if ($contents === false || array_diff($contents, ['.', '..']) || ! @rmdir($directory)) return;
            if ($normalized === $floor) return;
        }
    }

    protected function deleteFile(string $absolute): bool
    {
        return unlink($absolute);
    }
}
