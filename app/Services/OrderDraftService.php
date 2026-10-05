<?php

namespace App\Services;

use App\Http\Requests\CatalogOrderRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderDraftService
{
    public function key(bool $staff): string
    {
        return $staff ? 'staff_order_draft' : 'public_order_draft';
    }

    public function get(Request $request, bool $staff): ?array
    {
        if ($staff) {
            StaffAccess::requireOwner($request->user());
        }
        $draft = $request->session()->get($this->key($staff));
        if ($draft && empty($draft['submission_key'])) {
            $draft['submission_key'] = (string) Str::uuid();
            $request->session()->put($this->key($staff), $draft);
        }

        return $draft;
    }

    public function save(Request $request, bool $staff, CatalogPricingService $pricing): array
    {
        if ($staff) {
            StaffAccess::requireOwner($request->user());
        }
        $validated = $request->validate(CatalogOrderRules::items());
        $pricing->quote($validated['items']);
        $previous = $this->get($request, $staff);
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
                if ($old) {
                    unset($priorByKey[$key]);
                }
                $item['draft_key'] = $old ? $key : (string) Str::uuid();
                $saved = [];
                if (! $staff && $old && (int) $old['product_id'] === (int) $item['product_id']) {
                    $saved = $old['staged_images'] ?? [];
                    $removed = $raw['remove_staged_images'] ?? [];
                    $saved = array_values(array_filter($saved, fn ($image) => ! in_array($image['staged_path'], (array) $removed, true)));
                }
                $uploads = $item['images'] ?? [];
                if (count($saved) + count($uploads) > 5) {
                    throw ValidationException::withMessages(["items.{$index}.images" => 'Use up to five reference photos for each package.']);
                }
                unset($item['images']);
                if (! $staff) {
                    foreach ($uploads as $file) {
                        $path = Storage::disk('local')->putFileAs(
                            'order_drafts/'.$request->session()->getId(),
                            $file,
                            Str::uuid().'.'.$file->extension()
                        );
                        if ($path === false) {
                            throw ValidationException::withMessages(["items.{$index}.images" => 'Could not save the design reference. Please try again.']);
                        }
                        $newPaths[] = $path;
                        $saved[] = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName()];
                    }
                    $item['staged_images'] = $saved;
                }
                $items[] = $item;
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        $draft = ['items' => $items, 'details' => $previous['details'] ?? [], 'submission_key' => $previous['submission_key'] ?? (string) Str::uuid()];
        $request->session()->put($this->key($staff), $draft);
        $kept = [];
        foreach ($items as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                $kept[$image['staged_path']] = true;
            }
        }
        foreach ($previous['items'] ?? [] as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                if (! isset($kept[$image['staged_path']])) {
                    Storage::disk('local')->delete($image['staged_path']);
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
        $draft['details'] = $request->only($fields);
        $request->session()->put($this->key($staff), $draft);
    }

    public function finish(Request $request, bool $staff): void
    {
        $draft = $this->get($request, $staff);
        foreach ($draft['items'] ?? [] as $item) {
            foreach ($item['staged_images'] ?? [] as $image) {
                Storage::disk('local')->delete($image['staged_path']);
            }
        }
        $request->session()->forget($this->key($staff));
    }
}
