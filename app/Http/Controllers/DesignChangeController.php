<?php

namespace App\Http\Controllers;

use App\Models\{Order, OrderImage};
use App\Services\{DesignChangeDraftService, DesignChangeService, DesignChangeStore};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\ValidationException;

class DesignChangeController extends Controller
{
    public function __construct(private DesignChangeService $changes, private DesignChangeDraftService $drafts) {}

    private function order(string $token): Order
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        return Order::whereNull('user_id')->where('private_token', $token)->firstOrFail();
    }

    public function form(Request $request, string $token)
    {
        $order = $this->order($token);
        $summary = $this->changes->summary($order);
        if (! $this->changes->eligible($order) || $summary['hold'] || ! $summary['available']) {
            return redirect()->route('public.order.payment', $token)->with('success', 'Check your current design change and order status below.');
        }
        $draft = $this->drafts->start($request, $order);
        return view('public.design-change', compact('order', 'draft'));
    }

    public function saveDraft(Request $request, string $token)
    {
        $order = $this->order($token);
        $this->requireEditable($order);
        $draft = $this->drafts->save($request, $order);
        return response()->json(['saved' => true, 'draft_id' => $draft['id']]);
    }

    public function publicStatus(string $token)
    {
        return response()->json(['revision' => $this->changes->liveRevision($this->order($token))]);
    }

    public function staffStatus(Order $order)
    {
        Gate::authorize('view-records');
        return response()->json(['revision' => $this->changes->liveRevision($order)]);
    }

    public function upload(Request $request, string $token)
    {
        $order = $this->order($token);
        $this->requireEditable($order);
        $draft = $this->drafts->upload($request, $order);
        $images = [];
        foreach ($draft['items'][(int) $request->input('line_id')]['staged_images'] as $id) {
            $images[] = ['key' => $id, 'name' => $draft['uploads'][$id]['name'],
                'url' => route('public.designChanges.draftReference', ['token' => $token, 'draft' => $draft['id'], 'reference' => $id])];
        }
        return response()->json(['images' => $images]);
    }

    public function submit(Request $request, string $token, DesignChangeStore $store)
    {
        $order = $this->order($token);
        try {
            $request->validate(['draft_id' => ['required', 'uuid'], 'pause_acknowledged' => ['required', 'accepted']]);
            $state = $store->read($order, true);
            if (! isset($state['requests'][$request->input('draft_id')])) {
                $draft = $this->drafts->save($request, $order);
                $this->changes->submit($order, $draft, $draft['items']);
            }
        } catch (ValidationException $error) {
            throw $error->redirectTo(route('public.designChanges.form', $token));
        }
        return redirect(route('public.order.payment', $token).'#design-change')->with('success', 'Design change submitted. Preparation is paused until the bakery reviews it or you withdraw it.');
    }

    public function withdraw(string $token, string $change)
    {
        $order = $this->order($token);
        $this->changes->withdraw($order, $change);
        return redirect(route('public.order.payment', $token).'#design-change')->with('success', 'Change withdrawn. The bakery can resume preparation using your current design.');
    }

    public function approve(Request $request, Order $order, string $change)
    {
        Gate::authorize('review-design-changes');
        $request->validate(['feasibility_confirmed' => ['required', 'accepted']]);
        $this->changes->review($order, $change, 'approved', $request->user());
        return redirect(route('orders.show', $order).'#design-change')->with('success', 'Design change approved. Resume preparation using the updated design.');
    }

    public function decline(Request $request, Order $order, string $change)
    {
        Gate::authorize('review-design-changes');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        if (trim($data['reason']) === '') throw ValidationException::withMessages(['reason' => 'Explain why the change cannot be accepted.']);
        $this->changes->review($order, $change, 'declined', $request->user(), trim($data['reason']));
        return redirect(route('orders.show', $order).'#design-change')->with('success', 'Design change declined. Resume preparation using the current design.');
    }

    public function publicReference(string $token, string $change, string $reference)
    {
        return $this->file($this->changes->reference($this->order($token), $change, $reference));
    }

    public function staffReference(Order $order, string $change, string $reference)
    {
        Gate::authorize('view-records');
        return $this->file($this->changes->reference($order, $change, $reference));
    }

    public function draftReference(Request $request, string $token, string $draft, string $reference)
    {
        $request->merge(['draft_id' => $draft]);
        $order = $this->order($token);
        $state = $this->drafts->get($request, $order);
        app(\App\Services\CompletedOrderRetentionService::class)->assertPhotosAvailable($order);
        $image = $state['uploads'][$reference] ?? null;
        abort_unless($image, 404);
        return $this->file(['disk' => 'design_references', 'file_path' => $image['file_path'], 'name' => $image['name']]);
    }

    public function currentReference(string $token, OrderImage $image)
    {
        $order = $this->order($token);
        abort_unless((int) $image->order_id === $order->id, 404);
        app(\App\Services\CompletedOrderRetentionService::class)->assertPhotosAvailable($order);
        return $this->file(['disk' => $this->changes->imageDisk($image->file_path), 'file_path' => $image->file_path, 'name' => $image->original_filename]);
    }

    private function file(array $image)
    {
        $disk = Storage::disk($image['disk']);
        abort_unless($disk->exists($image['file_path']), 404);
        return $disk->response($image['file_path'], $image['name']);
    }

    private function requireEditable(Order $order): void
    {
        if (! $this->changes->eligible($order)) throw ValidationException::withMessages(['design_change' => 'This order no longer accepts design changes.']);
        $this->changes->assertNoPending($order);
    }
}
