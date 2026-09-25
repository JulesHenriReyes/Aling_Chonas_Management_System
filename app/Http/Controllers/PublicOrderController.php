<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CatalogPricingService;
use App\Services\OrderService;
use App\Services\OrderDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    public function index(Request $request, OrderDraftService $drafts)
    {
        $products = Product::active()->whereHas('options', fn ($query) => $query->where('is_active', true))
            ->with(['options' => fn ($query) => $query->where('is_active', true),
                'addOns' => fn ($query) => $query->where('is_active', true)])->orderBy('product_name')->get();

        $draft = $drafts->get($request, false);
        return view('public.index', compact('products', 'draft'));
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
        $quote = $pricing->quote($draft['items']);
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
        $contactRules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'min:7', 'max:20'],
        ];
        $draft = $drafts->get($request, false);
        if ($draft) {
            $data = $request->validate(array_diff_key(CatalogOrderRules::order(), CatalogOrderRules::items()) + $contactRules);
            $data['items'] = $drafts->itemsForOrder($draft);
        } else {
            $data = $request->validate(CatalogOrderRules::order() + $contactRules);
        }
        $order = DB::transaction(function () use ($data, $orders) {
            $customer = Customer::findOrCreateMatching($data);
            $data['customer_id'] = $customer->id;

            return $orders->createPublicOrder($data);
        });

        if ($draft) {
            $drafts->finish($request, false);
        }

        return redirect()->route('public.order.payment', $order->private_token);
    }

    public function success()
    {
        return redirect()->route('public.order.index')->with('info', 'Use your saved private order link to return to an existing order.');
    }
}
