<?php

namespace App\Http\Controllers;

use App\Models\{Supply, InventoryOperation, InventoryTransaction};
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};

class SupplyController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}
    public function index(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'in:ingredients,packaging'],
            'status' => ['nullable', 'in:healthy,low,out'], 'active' => ['nullable', 'in:active,inactive,all'],
            'sort' => ['nullable', 'in:supply_name,category,unit,current_quantity,reorder_level'], 'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $query = Supply::query();
        if ($request->input('active', 'active') !== 'all') $query->where('is_active', $request->input('active', 'active') === 'active');
        if ($request->filled('q')) $query->where('supply_name', 'like', '%'.$request->string('q').'%');
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->status === 'out') $query->where('current_quantity', 0);
        elseif ($request->status === 'low') $query->where('current_quantity', '>', 0)->whereColumn('current_quantity', '<=', 'reorder_level');
        elseif ($request->status === 'healthy') $query->where('current_quantity', '>', 0)->whereColumn('current_quantity', '>', 'reorder_level');
        if ($request->boolean('low_stock')) $query->lowStock();
        $supplies = $query->orderBy($request->input('sort', 'supply_name'), $request->input('direction', 'asc'))->orderBy('id')->paginate(20)->withQueryString();
        return view('admin.supplies.index', compact('supplies'));
    }
    public function create()
    {
        Gate::authorize('manage-inventory');
        return view('admin.supplies.form', ['supply' => new Supply]);
    }
    public function edit(Supply $supply)
    {
        Gate::authorize('manage-inventory');
        return view('admin.supplies.form', compact('supply'));
    }
    public function store(Request $request)
    {
        Gate::authorize('manage-inventory');
        $data = $this->validateSupply($request);
        $data += $request->validate(['current_quantity' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99']]);
        $supply = DB::transaction(function () use ($data) {
            $supply = Supply::create($data);
            $this->inventoryService->establishBaseline($supply);
            return $supply;
        });
        return redirect()->route('supplies.show', $supply)->with('success', 'Supply added with a recorded opening balance.');
    }
    public function update(Request $request, Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $data = $this->validateSupply($request, $supply);
        DB::transaction(function () use ($supply, $data) {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            if ($data['unit'] !== $supply->unit) throw ValidationException::withMessages(['unit' => 'The stock unit is fixed. Add a separate supply for a different unit.']);
            $supply->update($data);
        }, 5);
        return redirect()->route('supplies.show', $supply)->with('success', 'Supply updated.');
    }
    private function validateSupply(Request $request, ?Supply $supply = null): array
    {
        $data = $request->validate([
            'supply_name' => ['required', 'string', 'max:255', Rule::unique('supplies')->ignore($supply?->id)],
            'category' => ['required', 'in:ingredients,packaging'], 'unit' => ['required', 'string', 'max:50'],
            'reorder_level' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', !$supply);
        return $data;
    }
    public function show(Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $baseline = DB::table('inventory_baselines')->where('supply_id', $supply->id)->first();
        $netMovement = $supply->inventoryTransactions()->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'stock_out' THEN -quantity ELSE quantity END), 0) AS net")->value('net');
        $movements = $supply->inventoryTransactions()->with(['user', 'operation'])->latest('id')->paginate(20)->withQueryString();
        return view('admin.supplies.show', compact('supply', 'baseline', 'netMovement', 'movements'));
    }
    public function lookup(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        return Supply::active()->where('supply_name', 'like', '%'.$request->string('q').'%')->orderBy('supply_name')->limit(20)
            ->get(['id', 'supply_name', 'unit', 'current_quantity', 'stock_version']);
    }
    public function operationForm(Request $request, string $type)
    {
        Gate::authorize('manage-inventory');
        abort_unless(in_array($type, ['receipt', 'usage', 'waste', 'stocktake']), 404);
        $type = $request->old('type', $type);
        $lines = $request->old('lines', []);
        $selected = Supply::whereIn('id', array_slice(array_column($lines, 'supply_id'), 0, 100))->get()->keyBy('id');
        $initialLines = array_map(function ($line) use ($selected) {
            $supply = $selected->get($line['supply_id'] ?? null);
            return $line + ['name' => $supply?->supply_name ?? 'Unavailable supply', 'unit' => $supply?->unit,
                'current_quantity' => $supply?->current_quantity, 'expected_version' => $supply?->stock_version];
        }, $lines);
        return view('admin.supplies.operation-form', compact('type', 'initialLines'));
    }
    public function postOperation(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate(['type' => ['required', 'in:receipt,usage,waste,stocktake']]);
        $operation = $this->inventoryService->post($request->all(), $request->user());
        return redirect()->route('inventory.show', $operation)->with('success', 'Stock operation posted. All rows saved together.');
    }
    public function operation(InventoryOperation $operation)
    {
        Gate::authorize('manage-inventory');
        $operation->load(['user', 'movements.supply', 'movements.user', 'movements.operation', 'reversal', 'original']);
        return view('admin.supplies.operation', compact('operation'));
    }
    public function reverse(Request $request, InventoryOperation $operation)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['required', 'uuid'], 'notes' => ['required', 'string', 'max:2000']]);
        $reversal = $this->inventoryService->reverse($operation, $data['submission_key'], $data['notes'], $request->user());
        return redirect()->route('inventory.show', $reversal)->with('success', 'Linked reversal posted. Create a new operation with the corrected quantities if needed.');
    }
    public function history(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate(['q' => ['nullable', 'string', 'max:255'], 'type' => ['nullable', 'in:receipt,usage,waste,stocktake,adjustment,reversal'], 'supply_id' => ['nullable', 'integer']]);
        $query = InventoryTransaction::with(['supply', 'user', 'operation']);
        if ($request->filled('q')) $query->whereHas('supply', fn ($q) => $q->where('supply_name', 'like', '%'.$request->string('q').'%'));
        if ($request->filled('type')) $query->whereHas('operation', fn ($q) => $q->where('type', $request->type));
        if ($request->filled('supply_id')) $query->where('supply_id', $request->supply_id);
        return view('admin.supplies.history', ['movements' => $query->latest('id')->paginate(25)->withQueryString()]);
    }
    public function legacyMovement(InventoryTransaction $movement)
    {
        Gate::authorize('manage-inventory');
        if ($movement->inventory_operation_id) return redirect()->route('inventory.show', $movement->inventory_operation_id);
        $movement->load(['supply', 'user', 'operation', 'reversal.operation']);
        return view('admin.supplies.legacy-movement', compact('movement'));
    }
    public function reverseLegacy(Request $request, InventoryTransaction $movement)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['required', 'uuid'], 'notes' => ['required', 'string', 'max:2000']]);
        $reversal = $this->inventoryService->reverseLegacy($movement, $data['submission_key'], $data['notes'], $request->user());
        return redirect()->route('inventory.show', $reversal)->with('success', 'Linked reversal posted. The original legacy movement is preserved.');
    }
    public function recordTransaction(Request $request, Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['nullable', 'uuid'], 'transaction_type' => ['required', 'in:stock_in,stock_out,adjustment'], 'quantity' => ['required', 'numeric'], 'notes' => ['required_if:transaction_type,adjustment', 'nullable', 'string', 'max:2000']]);
        $this->inventoryService->recordTransaction($supply, $data['transaction_type'], (float) $data['quantity'], $request->user(), $data['notes'] ?? null, null, $data['submission_key'] ?? null);
        return back()->with('success', 'Movement recorded.');
    }
}
