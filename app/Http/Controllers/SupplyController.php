<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): View
    {
        $query = Supply::with(['inventoryTransactions' => fn ($q) => $q->latest()->take(5)])
            ->orderBy('supply_name');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('current_quantity', '<=', 'reorder_level');
        }

        $supplies = $query->get();

        return view('admin.supplies.index', compact('supplies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supply_name' => ['required', 'string', 'max:255', 'unique:supplies,supply_name'],
            'category' => ['required', Rule::in(['ingredients', 'packaging'])],
            'unit' => ['required', 'string', 'max:50'],
            'current_quantity' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        Supply::create($validated);

        return back()->with('success', "Supply '{$validated['supply_name']}' created successfully.");
    }

    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $validated = $request->validate([
            'supply_name' => ['required', 'string', 'max:255', 'unique:supplies,supply_name,' . $supply->id],
            'category' => ['required', Rule::in(['ingredients', 'packaging'])],
            'unit' => ['required', 'string', 'max:50'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);
        $supply->update($validated);

        return back()->with('success', "Supply '{$supply->supply_name}' updated successfully.");
    }

    public function recordTransaction(Request $request, Supply $supply): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_type' => ['required', Rule::in(['stock_in', 'stock_out', 'adjustment'])],
            'quantity' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->inventoryService->recordTransaction(
            $supply,
            $validated['transaction_type'],
            (float) $validated['quantity'],
            Auth::user(),
            $validated['notes'] ?? null
        );

        return back()->with('success', "Inventory transaction recorded for '{$supply->supply_name}'.");
    }
}
