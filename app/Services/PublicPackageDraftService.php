<?php

namespace App\Services;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicPackageDraftService
{
    public function editor(Request $request, Product $product, string $key): array
    {
        $request->validate(['line' => ['sometimes', 'uuid']]);
        abort_unless(Str::isUuid($key), 404);
        if ($request->session()->has('public_removed_lines.'.$key)) {
            throw ValidationException::withMessages(['items' => 'This package line was removed. Select a package to add a new line.']);
        }
        $draft = $request->session()->get('public_order_draft', []);
        $saved = collect($draft['items'] ?? [])->firstWhere('draft_key', $key);
        $editor = $request->session()->get('public_package_editors.'.$key);
        if ($editor) abort_unless((int) $editor['product_id'] === $product->id, 404);
        if ($saved) abort_unless((int) $saved['product_id'] === $product->id, 404);
        $editor ??= $saved ?? ['draft_key' => $key, 'product_id' => $product->id, 'package_option_id' => $product->options->first()?->id,
            'quantity' => 1, 'add_ons' => [], 'themes' => '', 'special_request' => '', 'staged_images' => []];
        $editors = $request->session()->get('public_package_editors', []);
        if (!isset($editors[$key]) && count($editors) >= 60) {
            throw ValidationException::withMessages(['items' => 'Too many open package drafts. Finish your current order before starting more.']);
        }
        $request->session()->put('public_package_editors.'.$key, $editor);
        return $editor;
    }

    public function remember(Request $request, Product $product, string $key): array
    {
        $editor = $this->editor($request, $product, $key);
        $raw = $request->input('items.0', []);
        $request->validate(['items' => ['required', 'array', 'size:1'], 'items.0' => ['required', 'array'],
            'items.0.themes' => ['nullable', 'string', 'max:2000'], 'items.0.special_request' => ['nullable', 'string', 'max:5000'],
            'items.0.add_ons' => ['nullable', 'array', 'max:50']]);
        $editor = array_replace($editor, Arr::only($raw, ['package_option_id', 'quantity', 'themes', 'special_request']));
        $editor['add_ons'] = $raw['add_ons'] ?? [];
        $request->session()->put('public_package_editors.'.$key, $editor);
        // Stage valid images before validating other fields so a quantity error cannot lose uploads.
        $request->validate(['items.0.images' => ['nullable', 'array', 'max:5'], 'items.0.images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $removed = (array) ($raw['remove_staged_images'] ?? []);
        $saved = array_values(array_filter($editor['staged_images'] ?? [], fn ($image) => !in_array($image['staged_path'], $removed, true)));
        $newPaths = [];
        try {
            foreach ($request->file('items.0.images', []) as $file) {
                $digest = hash_file('sha256', $file->getRealPath());
                if (collect($saved)->contains('digest', $digest)) continue;
                if (count($saved) >= 5) throw ValidationException::withMessages(['items.0.images' => 'Use up to five reference photos per package.']);
                $path = $file->store('order_drafts/'.$request->session()->getId(), 'local');
                if (!$path) throw ValidationException::withMessages(['items.0.images' => 'Could not save the reference. Try again.']);
                $newPaths[] = $path;
                $saved[] = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'digest' => $digest];
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }
        $editor['staged_images'] = $saved;
        // Keep files still referenced by a saved line until the edit is committed.
        $committedPaths = collect($request->session()->get('public_order_draft.items', []))->flatMap(fn ($line) => $line['staged_images'] ?? [])->pluck('staged_path');
        foreach ($removed as $path) {
            if (collect($request->session()->get('public_package_editors.'.$key.'.staged_images', []))->contains('staged_path', $path) && !$committedPaths->contains($path)) Storage::disk('local')->delete($path);
        }
        $request->session()->put('public_package_editors.'.$key, $editor);
        return $editor;
    }

    public function save(Request $request, Product $product, string $key, CatalogPricingService $pricing): array
    {
        $editor = $this->remember($request, $product, $key);
        $data = $request->validate(CatalogOrderRules::items());
        abort_unless((int) $data['items'][0]['product_id'] === $product->id, 422);
        $pricing->quote($data['items']);
        $item = $data['items'][0]; unset($item['images']);
        $item['draft_key'] = $key; $item['staged_images'] = $editor['staged_images'];
        $draft = $request->session()->get('public_order_draft', ['items' => [], 'details' => [], 'submission_key' => (string) Str::uuid()]);
        $index = collect($draft['items'])->search(fn ($line) => $line['draft_key'] === $key);
        if ($index === false) {
            if (count($draft['items']) >= 50) throw ValidationException::withMessages(['items' => 'Use at most 50 package lines per order.']);
            $draft['items'][] = $item;
        } else {
            foreach ($draft['items'][$index]['staged_images'] ?? [] as $image) {
                if (!collect($item['staged_images'])->contains('staged_path', $image['staged_path'])) Storage::disk('local')->delete($image['staged_path']);
            }
            $draft['items'][$index] = $item;
        }
        $request->session()->put('public_order_draft', $draft);
        $request->session()->put('public_package_editors.'.$key, $item);
        return $draft;
    }

    public function remove(Request $request, string $key): void
    {
        abort_unless(Str::isUuid($key), 404);
        $request->session()->put('public_removed_lines.'.$key, true);
        $draft = $request->session()->get('public_order_draft');
        if (!$draft) return;
        $line = collect($draft['items'])->firstWhere('draft_key', $key);
        foreach ($line['staged_images'] ?? [] as $image) Storage::disk('local')->delete($image['staged_path']);
        foreach ($request->session()->get('public_package_editors.'.$key.'.staged_images', []) as $image) Storage::disk('local')->delete($image['staged_path']);
        $draft['items'] = array_values(array_filter($draft['items'], fn ($item) => $item['draft_key'] !== $key));
        $request->session()->put('public_order_draft', $draft);
        $request->session()->forget('public_package_editors.'.$key);
    }
}
