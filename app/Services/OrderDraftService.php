<?php

namespace App\Services;

use App\Http\Requests\CatalogOrderRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderDraftService
{
    public function key(bool $staff, ?Request $request = null): string
    {
        return $staff ? 'staff_order_drafts.'.StaffAccess::requireOwner($request?->user())->id : 'public_order_draft';
    }

    public function put(Request $request, bool $staff, array $draft): void
    {
        $request->session()->put($this->key($staff, $request), $draft);
    }

    public function start(Request $request): array
    {
        if ($draft = $this->get($request, true)) return $draft;
        $owner = StaffAccess::requireOwner($request->user());
        $scope = $request->session()->get('staff_order_scope', (string) Str::uuid());
        $request->session()->put('staff_order_scope', $scope);
        $draft = ['draft_id' => (string) Str::uuid(), 'owner_id' => $owner->id, 'scope' => $scope,
            'submission_key' => (string) Str::uuid(), 'items' => [], 'details' => []];
        $this->put($request, true, $draft);
        $this->registerSubmission($request, $draft);
        return $draft;
    }

    private function registerSubmission(Request $request, array $draft): void
    {
        $request->session()->put('staff_submissions.'.$draft['owner_id'].'.'.$draft['submission_key'],
            ['scope' => $draft['scope'], 'draft_id' => $draft['draft_id']]);
    }

    public function submissionKey(Request $request, ?array $draft): string
    {
        $owner = StaffAccess::requireOwner($request->user());
        $request->validate(['submission_key' => ['nullable', 'uuid']]);
        $raw = $request->input('submission_key', $draft['submission_key'] ?? null);
        // Preserve the existing direct staff POST route; issue its key on the server.
        if (!$raw && $request->has('items')) {
            $draft = $this->start($request);
            $raw = $draft['submission_key'];
        }
        $issued = $raw ? $request->session()->get('staff_submissions.'.$owner->id.'.'.$raw) : null;
        if (!$issued) throw ValidationException::withMessages(['submission_key' => 'Open the staff order again before creating it. Your saved details are kept.']);
        return hash('sha256', 'staff|'.$owner->id.'|'.$issued['scope'].'|'.$raw);
    }

    public function submissionBrowserPrefix(Request $request, ?array $draft): ?string
    {
        $owner = StaffAccess::requireOwner($request->user());
        $raw = $request->input('submission_key', $draft['submission_key'] ?? null);
        $issued = $raw ? $request->session()->get('staff_submissions.'.$owner->id.'.'.$raw) : null;
        return $issued ? 'bakery-staff-'.$owner->id.'-'.$issued['scope'].'-'.$issued['draft_id'] : null;
    }

    public function get(Request $request, bool $staff): ?array
    {
        if ($staff) {
            StaffAccess::requireOwner($request->user());
        }
        $key = $this->key($staff, $request);
        $draft = $request->session()->get($key);
        if ($staff && !$draft && ($legacy = $request->session()->get('staff_order_draft'))) {
            $request->session()->forget('staff_order_draft');
            $scope = $request->session()->get('staff_order_scope', (string) Str::uuid());
            $request->session()->put('staff_order_scope', $scope);
            $draft = $legacy + ['draft_id' => (string) Str::uuid(), 'owner_id' => $request->user()->id, 'scope' => $scope];
            foreach ($draft['items'] as &$item) {
                $item['draft_key'] ??= (string) Str::uuid();
                $item['staged_images'] ??= [];
            }
            unset($item);
            $request->session()->put($key, $draft);
        }
        if ($staff && $draft) {
            abort_unless((int) $draft['owner_id'] === $request->user()->id && $draft['scope'] === $request->session()->get('staff_order_scope'), 403);
        }
        if ($draft && empty($draft['submission_key'])) {
            $draft['submission_key'] = (string) Str::uuid();
            $request->session()->put($key, $draft);
        }
        if ($staff && $draft) $this->registerSubmission($request, $draft);

        return $draft;
    }

    public function save(Request $request, bool $staff, CatalogPricingService $pricing): array
    {
        if ($staff) {
            StaffAccess::requireOwner($request->user());
        }
        $validated = $request->validate(CatalogOrderRules::items());
        $pricing->quote($validated['items']);
        $previous = $staff ? $this->start($request) : $this->get($request, false);
        $items = [];
        $newPaths = [];
        $priorByKey = [];
        foreach ($previous['items'] ?? [] as $prior) {
            if (isset($prior['draft_key'])) {
                $priorByKey[$prior['draft_key']] = $prior;
            }
        }

        try {
            foreach (array_values($validated['items']) as $index => $item) {
                $raw = $request->input("items.{$index}", []);
                $key = $raw['draft_key'] ?? null;
                $old = $key && isset($priorByKey[$key]) ? $priorByKey[$key] : null;
                if ($staff && $key && (!$old || isset($previous['removed_lines'][$key]))) {
                    throw ValidationException::withMessages(["items.{$index}.draft_key" => 'This package line is not part of the current staff order. Select a package to add it.']);
                }
                if ($staff && isset($old['catalog_signature'])) {
                    throw ValidationException::withMessages(["items.{$index}.draft_key" => 'Edit this saved package on its customization page to review the current catalog and preserve its photos.']);
                }
                if ($old) {
                    unset($priorByKey[$key]);
                }
                $item['draft_key'] = $old ? $key : (string) Str::uuid();
                $saved = [];
                if ($old && (int) $old['product_id'] === (int) $item['product_id']) {
                    $saved = $old['staged_images'] ?? [];
                    $removed = $raw['remove_staged_images'] ?? [];
                    $saved = array_values(array_filter($saved, fn ($image) => ! in_array($image['staged_path'], (array) $removed, true)));
                }
                $uploads = $item['images'] ?? [];
                if (count($saved) + count($uploads) > 5) {
                    throw ValidationException::withMessages(["items.{$index}.images" => 'Use up to five reference photos for each package.']);
                }
                unset($item['images']);
                {
                    foreach ($uploads as $file) {
                        $path = Storage::disk($staff ? 'staff_drafts' : 'local')->putFileAs(
                            $staff ? $previous['owner_id'].'/'.$previous['scope'].'/'.$previous['draft_id'].'/'.$item['draft_key'] : 'order_drafts/'.$request->session()->getId(),
                            $file,
                            Str::uuid().'.'.$file->extension()
                        );
                        if ($path === false) {
                            throw ValidationException::withMessages(["items.{$index}.images" => 'Could not save the design reference. Please try again.']);
                        }
                        $newPaths[] = $path;
                        $saved[] = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName()] + ($staff ? ['staged_disk' => 'staff_drafts', 'image_id' => (string) Str::uuid()] : []);
                    }
                    $item['staged_images'] = $saved;
                }
                $items[] = $item;
            }
        } catch (\Throwable $exception) {
            Storage::disk($staff ? 'staff_drafts' : 'local')->delete($newPaths);
            throw $exception;
        }

        $draft = array_replace($previous ?? [], ['items' => $items, 'details' => $previous['details'] ?? [], 'submission_key' => $previous['submission_key'] ?? (string) Str::uuid()]);
        $this->put($request, $staff, $draft);
        $kept = [];
        foreach ($items as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                $kept[$image['staged_path']] = true;
            }
        }
        foreach ($previous['items'] ?? [] as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                if (! isset($kept[$image['staged_path']])) {
                    Storage::disk($image['staged_disk'] ?? 'local')->delete($image['staged_path']);
                }
            }
        }

        return $draft;
    }

    public function itemsForOrder(array $draft): array
    {
        return array_map(function (array $item) {
            $item['images'] = $item['staged_images'] ?? [];
            unset($item['staged_images'], $item['draft_key']);

            return $item;
        }, $draft['items']);
    }

    public function saveDetails(Request $request, bool $staff): void
    {
        $draft = $this->get($request, $staff);
        if (! $draft) {
            return;
        }
        $fields = $staff
            ? ['customer_id', 'pickup_date', 'pickup_time', 'notes_text']
            : ['first_name', 'middle_name', 'last_name', 'phone_number', 'pickup_date', 'pickup_time', 'notes_text'];
        $draft['details'] = array_replace($draft['details'] ?? [], $request->only($fields));
        if ($staff && $request->has('customer_picker')) {
            $draft['customer_picker'] = $request->input('customer_picker');
        }
        $this->put($request, $staff, $draft);
    }

    public function finish(Request $request, bool $staff): void
    {
        $draft = $this->get($request, $staff);
        foreach ($draft['items'] ?? [] as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                Storage::disk($image['staged_disk'] ?? 'local')->delete($image['staged_path']);
            }
        }
        if ($staff) {
            foreach ($draft['package_editors'] ?? [] as $editor) {
                foreach ($editor['staged_images'] ?? [] as $image) Storage::disk($image['staged_disk'] ?? 'staff_drafts')->delete($image['staged_path']);
            }
        }
        $request->session()->forget($this->key($staff, $request));
    }
}
