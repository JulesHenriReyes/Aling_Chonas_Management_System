<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DesignChangeDraftService
{
    public function __construct(private DesignChangeService $changes, private DesignChangeStore $store) {}

    private function key(Order $order): string
    {
        return 'design_change_drafts.'.$this->store->identity($order);
    }

    public function start(Request $request, Order $order): array
    {
        $key = $this->key($order);
        $draft = $request->session()->get($key);
        $state = $this->store->read($order);
        if (! $draft || $request->boolean('restart') || isset($state['requests'][$draft['id']])) {
            $snapshot = $this->changes->snapshot($order);
            $items = [];
            foreach ($snapshot as $line) $items[$line['id']] = ['themes' => $line['themes'], 'special_request' => $line['special_request'],
                'keep_images' => array_column($line['references'], 'id'), 'staged_images' => []];
            $draft = ['id' => (string) Str::uuid(), 'fingerprint' => $this->changes->fingerprint($snapshot),
                'snapshot' => $snapshot, 'items' => $items, 'uploads' => []];
            $request->session()->put($key, $draft);
        }
        return $draft;
    }

    public function get(Request $request, Order $order): array
    {
        $request->validate(['draft_id' => ['required', 'uuid']]);
        $draft = $request->session()->get($this->key($order));
        abort_unless($draft && $draft['id'] === $request->input('draft_id'), 404);
        return $draft;
    }

    public function save(Request $request, Order $order): array
    {
        $draft = $this->get($request, $order);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:themes,special_request,keep_images,staged_images'],
            'items.*.themes' => ['nullable', 'string', 'max:255'],
            'items.*.special_request' => ['nullable', 'string', 'max:1000'],
            'items.*.keep_images' => ['sometimes', 'array', 'max:5'],
            'items.*.keep_images.*' => ['required', 'integer', 'distinct'],
            'items.*.staged_images' => ['sometimes', 'array', 'max:5'],
            'items.*.staged_images.*' => ['required', 'uuid', 'distinct'],
        ]);
        $actual = array_map('intval', array_keys($data['items']));
        $expected = array_column($draft['snapshot'], 'id');
        sort($actual); sort($expected);
        if ($actual !== $expected) throw ValidationException::withMessages(['design_change' => 'Choose packages belonging to this order.']);
        $draft['items'] = $data['items'];
        $request->session()->put($this->key($order), $draft);
        return $draft;
    }

    public function upload(Request $request, Order $order): array
    {
        $draft = $this->get($request, $order);
        $data = $request->validate(['line_id' => ['required', 'integer'], 'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $line = (int) $data['line_id'];
        abort_unless(in_array($line, array_column($draft['snapshot'], 'id'), true), 404);
        $activeKeys = $draft['items'][$line]['staged_images'] ?? [];
        if (count($activeKeys) + count($request->file('images')) > 5) {
            throw ValidationException::withMessages(['images' => 'Upload up to five new photos per package. Remove unused uploaded photos first.']);
        }
        $written = [];
        try {
            foreach ($request->file('images') as $file) {
                $digest = hash_file('sha256', $file->getRealPath());
                $existing = collect($draft['uploads'])->first(fn ($image) => $image['line_id'] === $line && $image['digest'] === $digest);
                if ($existing) {
                    if (! in_array($existing['key'], $activeKeys, true)) $activeKeys[] = $existing['key'];
                    continue;
                }
                $id = (string) Str::uuid();
                $path = $file->storeAs('design-changes/'.$this->store->identity($order).'/'.$draft['id'], $id.'.'.$file->extension(), 'design_references');
                $written[] = $path;
                $draft['uploads'][$id] = ['key' => $id, 'line_id' => $line, 'file_path' => $path,
                    'name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'digest' => $digest];
                $activeKeys[] = $id;
            }
        } catch (\Throwable $error) {
            Storage::disk('design_references')->delete($written);
            throw $error;
        }
        $draft['items'][$line]['staged_images'] = array_values(array_unique($activeKeys));
        $request->session()->put($this->key($order), $draft);
        return $draft;
    }
}
