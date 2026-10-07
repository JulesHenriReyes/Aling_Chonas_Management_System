<?php

namespace App\Services;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class PackageDraftService
{
    private function draft(Request $request, bool $staff): array
    {
        $drafts = app(OrderDraftService::class);
        $draft = $staff ? $drafts->start($request) : ($drafts->get($request, false) ?? []);
        if ($staff && $request->has('draft_id')) abort_unless(hash_equals($draft['draft_id'], (string) $request->input('draft_id')), 404);
        return $draft;
    }

    private function stateKey(Request $request, bool $staff, string $part): string
    {
        return $staff ? app(OrderDraftService::class)->key(true, $request).'.'.$part : 'public_'.$part;
    }

    public function issueLine(Request $request, Product $product): string
    {
        $draft = $this->draft($request, true);
        $offered = $draft['offered_lines'] ?? [];
        foreach ($offered as $key => $productId) if ((int) $productId === $product->id) return $key;
        $key = (string) Str::uuid();
        $offered[$key] = $product->id;
        $request->session()->put($this->stateKey($request, true, 'offered_lines'), array_slice($offered, -100, null, true));
        return $key;
    }

    public function editor(Request $request, Product $product, string $key, bool $staff = false, bool $opening = true): array
    {
        abort_unless(Str::isUuid($key), 404);
        $draft = $this->draft($request, $staff);
        if ($request->session()->has($this->stateKey($request, $staff, 'removed_lines').'.'.$key)) {
            throw ValidationException::withMessages(['items' => 'This package line was removed. Select a package to add a new line.']);
        }
        $saved = collect($draft['items'] ?? [])->firstWhere('draft_key', $key);
        $editorsKey = $this->stateKey($request, $staff, 'package_editors');
        $editor = $request->session()->get($editorsKey.'.'.$key);
        if ($staff) abort_unless($saved || $editor || ($opening && (int) ($draft['offered_lines'][$key] ?? 0) === $product->id), 404);
        if ($editor) abort_unless((int) $editor['product_id'] === $product->id, 404);
        if ($saved) abort_unless((int) $saved['product_id'] === $product->id, 404);
        $editor ??= $saved ?? ['draft_key' => $key, 'product_id' => $product->id, 'package_option_id' => $product->options->first()?->id,
            'quantity' => 1, 'add_ons' => [], 'themes' => '', 'special_request' => '', 'staged_images' => []];
        if (!$request->session()->has($editorsKey.'.'.$key) && count($request->session()->get($editorsKey, [])) >= 60) {
            throw ValidationException::withMessages(['items' => 'Too many open package drafts. Finish this order before starting more.']);
        }
        if ($staff && $opening) {
            $editor['catalog_snapshot'] = $this->catalogSnapshot($product);
            $request->session()->forget($this->stateKey($request, true, 'offered_lines').'.'.$key);
        }
        $request->session()->put($editorsKey.'.'.$key, $editor);
        return $editor;
    }

    private function catalogSnapshot(Product $product): array
    {
        return ['options' => $product->options()->with('includedItems')->get()->keyBy('id')->map(fn ($option) => [
            'price' => (int) round((float) $option->price * 100), 'active' => (bool) $option->is_active,
            'layers' => $option->layers, 'contents' => $option->included_contents,
            'included' => $option->includedItems->map(fn ($item) => [$item->id, (int) $item->pivot->quantity])->sortBy(0)->values()->all(),
        ])->all(), 'extras' => $product->addOns()->get()->keyBy('id')->map(fn ($extra) => [
            'price' => (int) round((float) $extra->price * 100), 'active' => (bool) $extra->is_active,
        ])->all()];
    }

    public function signature(array $line): string
    {
        $extras = collect($line['add_ons'])->map(fn ($extra) => [$extra['add_on_id'], (int) round($extra['unit_price'] * 100)])->sortBy(0)->values()->all();
        $included = collect($line['included_items_snapshot'])->map(fn ($item) => [$item['add_on_id'], $item['quantity']])->sortBy(0)->values()->all();
        return hash('sha256', json_encode([$line['product_id'], $line['package_option_id'], $line['layers'],
            (int) round($line['unit_price'] * 100), $line['included_contents_snapshot'], $included, $extras], JSON_THROW_ON_ERROR));
    }

    public function remember(Request $request, Product $product, string $key, bool $staff = false): array
    {
        $editor = $this->editor($request, $product, $key, $staff, !$staff);
        $request->validate(['items' => ['required', 'array', 'size:1'], 'items.0' => ['required', 'array'],
            'items.0.themes' => ['nullable', 'string', 'max:2000'], 'items.0.special_request' => ['nullable', 'string', 'max:5000'],
            'items.0.add_ons' => ['nullable', 'array', 'max:50'], 'items.0.remove_staged_images' => ['nullable', 'array', 'max:5'],
            'items.0.remove_staged_images.*' => ['required', 'string', 'max:300']]);
        $raw = $request->input('items.0');
        if ($staff) abort_unless((int) ($raw['product_id'] ?? 0) === $product->id && ($raw['draft_key'] ?? $key) === $key, 422);
        $editor = array_replace($editor, Arr::only($raw, ['package_option_id', 'quantity', 'themes', 'special_request']));
        $editor['add_ons'] = $raw['add_ons'] ?? [];
        $editorKey = $this->stateKey($request, $staff, 'package_editors').'.'.$key;
        // Remember text and stage valid photos before full package validation.
        $request->session()->put($editorKey, $editor);
        $request->validate(['items.0.images' => ['nullable', 'array', 'max:5'],
            'items.0.images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $removed = $raw['remove_staged_images'] ?? [];
        if ($staff && array_diff($removed, array_column($editor['staged_images'] ?? [], 'staged_path'))) {
            throw ValidationException::withMessages(['items.0.images' => 'Choose a reference photo belonging to this package draft.']);
        }
        $saved = array_values(array_filter($editor['staged_images'] ?? [], fn ($image) => !in_array($image['staged_path'], $removed, true)));
        $disk = $staff ? 'staff_drafts' : 'local';
        $draft = $this->draft($request, $staff);
        $directory = $staff ? $draft['owner_id'].'/'.$draft['scope'].'/'.$draft['draft_id'].'/'.$key : 'order_drafts/'.$request->session()->getId();
        $newPaths = [];
        try {
            foreach ($request->file('items.0.images', []) as $file) {
                $digest = hash_file('sha256', $file->getRealPath());
                if (collect($saved)->contains('digest', $digest)) continue;
                if (count($saved) >= 5) throw ValidationException::withMessages(['items.0.images' => 'Use up to five reference photos per package.']);
                $path = $file->store($directory, $disk);
                if (!$path) throw ValidationException::withMessages(['items.0.images' => 'Could not save the reference. Try again.']);
                $newPaths[] = $path;
                $image = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'digest' => $digest];
                if ($staff) $image += ['staged_disk' => $disk, 'image_id' => (string) Str::uuid()];
                $saved[] = $image;
            }
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($newPaths);
            throw $exception;
        }
        $committedPaths = collect($draft['items'] ?? [])->flatMap(fn ($line) => $line['staged_images'] ?? [])->pluck('staged_path');
        foreach ($removed as $path) {
            if (collect($editor['staged_images'] ?? [])->contains('staged_path', $path) && !$committedPaths->contains($path)) Storage::disk($disk)->delete($path);
        }
        $editor['staged_images'] = $saved;
        $request->session()->put($editorKey, $editor);
        return $editor;
    }

    public function save(Request $request, Product $product, string $key, CatalogPricingService $pricing, bool $staff = false): array
    {
        $editor = $this->remember($request, $product, $key, $staff);
        $data = $request->validate(CatalogOrderRules::items());
        abort_unless((int) $data['items'][0]['product_id'] === $product->id, 422);
        $quote = DB::transaction(function () use ($data, $pricing, $staff, $editor, $product) {
            $quote = $pricing->quote($data['items']);
            if ($staff) {
                $fresh = $this->catalogSnapshot($product);
                $optionId = $data['items'][0]['package_option_id'];
                $changed = ($fresh['options'][$optionId] ?? null) !== ($editor['catalog_snapshot']['options'][$optionId] ?? null);
                foreach ($data['items'][0]['add_ons'] ?? [] as $extra) {
                    $id = $extra['add_on_id'];
                    $changed = $changed || ($fresh['extras'][$id] ?? null) !== ($editor['catalog_snapshot']['extras'][$id] ?? null);
                }
                if ($changed) throw ValidationException::withMessages(['items.0.package_option_id' => 'The catalog changed. Your details and photos are kept. Review this package’s current prices and inclusions, then save again.']);
            }
            return $quote;
        });
        $item = $data['items'][0];
        unset($item['images']);
        $item['draft_key'] = $key;
        $item['staged_images'] = $editor['staged_images'];
        if ($staff) $item['catalog_signature'] = $this->signature($quote['lines'][0]);
        $draft = $this->draft($request, $staff);
        $draft += ['items' => [], 'details' => [], 'submission_key' => (string) Str::uuid()];
        $index = collect($draft['items'])->search(fn ($line) => $line['draft_key'] === $key);
        if ($index === false) {
            if (count($draft['items']) >= 50) throw ValidationException::withMessages(['items' => 'Use at most 50 package lines per order.']);
            $draft['items'][] = $item;
        } else {
            foreach ($draft['items'][$index]['staged_images'] ?? [] as $image) {
                if (!collect($item['staged_images'])->contains('staged_path', $image['staged_path'])) Storage::disk($staff ? 'staff_drafts' : 'local')->delete($image['staged_path']);
            }
            $draft['items'][$index] = $item;
        }
        app(OrderDraftService::class)->put($request, $staff, $draft);
        $item['catalog_snapshot'] = $editor['catalog_snapshot'] ?? [];
        $request->session()->put($this->stateKey($request, $staff, 'package_editors').'.'.$key, $item);
        return $draft;
    }

    public function remove(Request $request, string $key, bool $staff = false): void
    {
        abort_unless(Str::isUuid($key), 404);
        $draft = $this->draft($request, $staff);
        $removedKey = $this->stateKey($request, $staff, 'removed_lines').'.'.$key;
        if ($request->session()->has($removedKey)) return;
        $line = collect($draft['items'] ?? [])->firstWhere('draft_key', $key);
        $editorKey = $this->stateKey($request, $staff, 'package_editors').'.'.$key;
        $editor = $request->session()->get($editorKey);
        if ($staff) abort_unless($line || $editor, 404);
        foreach (array_merge($line['staged_images'] ?? [], $editor['staged_images'] ?? []) as $image) {
            Storage::disk($staff ? 'staff_drafts' : 'local')->delete($image['staged_path']);
        }
        $draft['items'] = array_values(array_filter($draft['items'] ?? [], fn ($item) => $item['draft_key'] !== $key));
        app(OrderDraftService::class)->put($request, $staff, $draft);
        $request->session()->put($removedKey, true);
        $request->session()->forget($editorKey);
    }

    public function imagesForBrowser(Request $request, array $editor, bool $staff): array
    {
        $draft = $staff ? $this->draft($request, true) : [];
        return array_map(function ($image) use ($editor, $staff, $draft) {
            $image['preview_url'] = $staff ? route('orders.package.image', ['draft' => $draft['draft_id'],
                'line' => $editor['draft_key'], 'image' => $image['image_id']]) : url('/storage/'.$image['staged_path']);
            return $image;
        }, $editor['staged_images'] ?? []);
    }

    public function context(Request $request, bool $staff, ?array $draft = null): array
    {
        $draft ??= $staff ? $this->draft($request, true) : (app(OrderDraftService::class)->get($request, false) ?? []);
        return ['staff' => $staff, 'select_route' => $staff ? 'orders.create' : 'public.order.index',
            'customize_route' => $staff ? 'orders.package.customize' : 'public.package.customize',
            'save_route' => $staff ? 'orders.package.save' : 'public.package.save',
            'editor_route' => $staff ? 'orders.package.draft' : 'public.package.draft',
            'remove_route' => $staff ? 'orders.package.remove' : 'public.package.remove',
            'details_route' => $staff ? 'orders.details' : 'public.order.details',
            'quote_url' => route($staff ? 'orders.package.quote' : 'public.order.quote'),
            'summary_title' => $staff ? 'Staff order' : 'Your order',
            'continue_label' => $staff ? 'Continue to customer and pickup' : 'Continue to contact and pickup',
            'draft_id' => $draft['draft_id'] ?? null,
            'browser_prefix' => $staff ? 'bakery-staff-'.$draft['owner_id'].'-'.$draft['scope'].'-'.$draft['draft_id'] : 'bakery-package'];
    }
}
