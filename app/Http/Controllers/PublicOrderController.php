<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CatalogPricingService;
use App\Services\OrderService;
use App\Services\OrderDraftService;
use App\Services\PublicPackageDraftService;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    public function index(Request $request, OrderDraftService $drafts)
    {
        $products = Product::active()->whereHas('options', fn ($query) => $query->where('is_active', true))
            ->with(['options' => fn ($query) => $query->where('is_active', true)->with('includedItems'),
                'addOns' => fn ($query) => $query->where('is_active', true)])->orderBy('product_name')->get();

        $draft = $drafts->get($request, false);
        $draftLines = [];
        foreach ($draft['items'] ?? [] as $item) {
            try { $quote = app(CatalogPricingService::class)->quote([$item]); $error = null; }
            catch (ValidationException $exception) { $quote = null; $error = collect($exception->errors())->flatten()->first(); }
            $draftLines[] = ['item' => $item, 'quote' => $quote, 'error' => $error,
                'product' => $products->firstWhere('id', $item['product_id']) ?? Product::find($item['product_id'])];
        }
        return view('public.index', compact('products', 'draft', 'draftLines'));
    }

    public function customize(Request $request, Product $product, string $line, PublicPackageDraftService $editors)
    {
        if ($request->session()->has('public_removed_lines.'.$line)) return redirect()->route('public.order.index')->with('success', 'This package line was removed. Select a package to add a new line.');
        abort_unless($product->is_active, 404);
        $product->load(['options' => fn ($q) => $q->where('is_active', true)->with('includedItems'), 'addOns' => fn ($q) => $q->where('is_active', true)]);
        abort_if($product->options->isEmpty(), 404);
        $editor = $editors->editor($request, $product, $line);
        return view('public.customize', ['products' => collect([$product]), 'product' => $product, 'line' => $line, 'editor' => $editor]);
    }

    public function savePackage(Request $request, Product $product, string $line, PublicPackageDraftService $editors, CatalogPricingService $pricing)
    {
        $product->load('options');
        $editors->save($request, $product, $line, $pricing);
        return redirect()->route('public.order.index', ['saved_line' => $line])->with('success', 'Package saved to your order.');
    }

    public function saveEditor(Request $request, Product $product, string $line, PublicPackageDraftService $editors)
    {
        $product->load('options');
        $editor = $editors->remember($request, $product, $line);
        return response()->json(['staged_images' => $editor['staged_images']]);
    }

    public function removePackage(Request $request, string $line, PublicPackageDraftService $editors)
    {
        $editors->remove($request, $line);
        return redirect()->route('public.order.index')->with('success', 'Package removed.');
    }

    public function saveSelection(Request $request, OrderDraftService $drafts, CatalogPricingService $pricing)
    {
        $drafts->save($request, false, $pricing);
        return redirect()->route('public.order.details');
    }

    public function details(Request $request, OrderDraftService $drafts, CatalogPricingService $pricing)
    {
        $draft = $drafts->get($request, false);
        if (!$draft || empty($draft['items'])) {
            return redirect()->route('public.order.index');
        }
        try { $quote = $pricing->quote($draft['items']); }
        catch (ValidationException $exception) { return redirect()->route('public.order.index')->withErrors($exception->errors()); }
        return view('public.details', compact('draft', 'quote'));
    }

    public function backToSelection(Request $request, OrderDraftService $drafts)
    {
        $drafts->saveDetails($request, false);
        return redirect()->route('public.order.index');
    }

    public function quote(Request $request, CatalogPricingService $pricing)
    {
        $data = $request->validate(CatalogOrderRules::items());

        return response()->json(DB::transaction(fn () => $pricing->quote($data['items'])));
    }

    public function store(Request $request, OrderService $orders, OrderDraftService $drafts)
    {
        $request->validate(['submission_key' => ['nullable', 'uuid']]);
        $draft = $drafts->get($request, false);
        $rawKey = $request->input('submission_key', $draft['submission_key'] ?? hash('sha256', json_encode($request->except('_token'))));
        $scope = $request->session()->get('public_checkout_scope', (string) Str::uuid());
        $request->session()->put('public_checkout_scope', $scope);
        $submissionKey = hash('sha256', $scope.'|'.$rawKey);
        if ($existing = Order::where('submission_key', $submissionKey)->first()) {
            return redirect()->route('public.order.payment', $existing->private_token);
        }
        $drafts->saveDetails($request, false);
        $contactRules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'min:7', 'max:20'],
        ];
        if ($draft) {
            $data = $request->validate(array_diff_key(CatalogOrderRules::order(), CatalogOrderRules::items()) + $contactRules);
            $data['items'] = $drafts->itemsForOrder($draft);
        } else {
            $data = $request->validate(CatalogOrderRules::order() + $contactRules);
        }
        $data['submission_key'] = $submissionKey;
        try { $order = DB::transaction(function () use ($data, $orders) {
            $customer = Customer::findOrCreateMatching($data);
            $data['customer_id'] = $customer->id;

            return $orders->createPublicOrder($data);
        }); } catch (UniqueConstraintViolationException $exception) {
            $order = Order::where('submission_key', $submissionKey)->first();
            if (!$order) throw $exception;
        }

        if ($draft) {
            $drafts->finish($request, false);
            foreach ($request->session()->get('public_package_editors', []) as $editor) {
                foreach ($editor['staged_images'] ?? [] as $image) \Illuminate\Support\Facades\Storage::disk('local')->delete($image['staged_path']);
            }
            $request->session()->forget('public_package_editors');
        }

        return redirect()->route('public.order.payment', $order->private_token);
    }

    public function success()
    {
        return redirect()->route('public.order.index')->with('info', 'Use your saved private order link to return to an existing order.');
    }
}
