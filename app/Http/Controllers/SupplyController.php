<?php

namespace App\Http\Controllers;

use App\Models\{Supply, InventoryOperation, InventoryTransaction, StockEntry};
use App\Services\{InventoryService, StockEntryLedger, StaffAccess};
use App\Support\InventoryCalendar;
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
            'expiry' => ['nullable', 'in:available,expiring,expired,unknown'],
            'sort' => ['nullable', 'in:expiry,supply_name,category,unit,current_quantity,reorder_level'], 'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $query = Supply::withUsableQuantity()->with(['stockEntries' => fn ($q) => $q->where('remaining_quantity', '>', 0)->orderBy('expiry_date')->orderBy('stock_in_date')->orderBy('id')]);
        if ($request->input('active', 'active') !== 'all') $query->where('is_active', $request->input('active', 'active') === 'active');
        if ($request->filled('q')) $query->where('supply_name', 'like', '%'.$request->string('q').'%');
        if ($request->filled('category')) $query->where('category', $request->category);
        $usable = Supply::usableSql(); $today = InventoryCalendar::date();
        if ($request->status === 'out') $query->whereRaw($usable.' = 0', [$today]);
        elseif ($request->status === 'low') $query->whereRaw($usable.' > 0 AND '.$usable.' <= supplies.reorder_level', [$today, $today]);
        elseif ($request->status === 'healthy') $query->whereRaw($usable.' > supplies.reorder_level', [$today]);
        if ($request->filled('expiry')) $query->where('category','ingredients')->whereHas('stockEntries', fn ($q) => $this->expiryQuery($q, $request->expiry));
        if ($request->boolean('low_stock')) $query->lowStock();
        $sort = $request->input('sort', 'expiry');
        if ($sort === 'expiry') {
            $dir = $request->input('direction', 'asc') === 'desc' ? 'DESC' : 'ASC';
            $warningDate = InventoryCalendar::today()->addDays(config('inventory.expiry_warning_days', 7))->toDateString();
            $supplies = $query->orderByRaw("
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM stock_entries se
                        WHERE se.supply_id = supplies.id
                          AND se.remaining_quantity > 0
                          AND se.expiry_date >= ?
                          AND se.expiry_date <= ?
                    ) THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM stock_entries se
                        WHERE se.supply_id = supplies.id
                          AND se.remaining_quantity > 0
                          AND se.expiry_date > ?
                    ) THEN 1
                    WHEN EXISTS (
                        SELECT 1 FROM stock_entries se
                        WHERE se.supply_id = supplies.id
                          AND se.remaining_quantity > 0
                          AND se.expiry_date < ?
                    ) THEN 2
                    WHEN EXISTS (
                        SELECT 1 FROM stock_entries se
                        WHERE se.supply_id = supplies.id
                          AND se.remaining_quantity > 0
                          AND se.expiry_date IS NULL
                          AND supplies.category = 'ingredients'
                    ) THEN 3
                    ELSE 4
                END {$dir}
            ", [$today, $warningDate, $warningDate, $today])
            ->orderByRaw("
                (SELECT MIN(se.expiry_date)
                 FROM stock_entries se
                 WHERE se.supply_id = supplies.id
                   AND se.remaining_quantity > 0
                   AND se.expiry_date >= ?) {$dir}
            ", [$today])
            ->orderBy('supply_name', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(20)
            ->withQueryString();
        } else {
            $supplies = $query->orderBy($sort === 'current_quantity' ? 'available_quantity' : $sort, $request->input('direction', 'asc'))->orderBy('id')->paginate(20)->withQueryString();
        }
        $expiryCounts = [];
        foreach (['expiring','expired','unknown'] as $state) $expiryCounts[$state] = $this->expiryQuery(StockEntry::whereHas('supply', fn ($q) => $q->active()->where('category','ingredients')), $state)->count();
        return view('admin.supplies.index', compact('supplies','expiryCounts'));
    }
    private function expiryQuery($query, string $state)
    {
        $query->where('remaining_quantity','>',0);
        return match ($state) {
            'unknown' => $query->whereNull('expiry_date'),
            'expired' => $query->where('expiry_date','<',InventoryCalendar::date()),
            'expiring' => $query->whereBetween('expiry_date',[InventoryCalendar::date(),InventoryCalendar::today()->addDays(config('inventory.expiry_warning_days',7))->toDateString()]),
            default => $query->where('expiry_date','>=',InventoryCalendar::date()),
        };
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
        // Quick creation defines an item; the stock form records its quantity later.
        $data += $request->validate(['current_quantity' => ['required', 'numeric', 'decimal:0,2',
            $request->expectsJson() || $data['category'] === 'ingredients' ? 'size:0' : 'between:0,99999999.99']],
            ['current_quantity.size' => 'Create the supply with zero stock, then record its quantity through Stock in.']);
        $supply = DB::transaction(function () use ($data) {
            $supply = Supply::create($data);
            $this->inventoryService->establishBaseline($supply);
            return $supply;
        });
        if ($request->expectsJson()) {
            return response()->json($this->supplyData($supply->fresh()), 201);
        }
        if ($request->input('next') === 'stock_in' && $supply->is_active) {
            return redirect()->route('inventory.create', ['type' => 'receipt', 'supply_id' => $supply->id])
                ->with('success', 'Supply added. Enter the quantity to stock in.');
        }
        return redirect()->route('supplies.show', $supply)->with('success', 'Supply added with a recorded opening balance.');
    }
    public function update(Request $request, Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $data = $this->validateSupply($request, $supply);
        DB::transaction(function () use ($supply, $data) {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            if ($data['unit'] !== $supply->unit) throw ValidationException::withMessages(['unit' => 'The stock unit is fixed. Add a separate supply for a different unit.']);
            if ($data['category'] !== $supply->category && ($supply->stockEntries()->exists() || $supply->inventoryTransactions()->exists())) throw ValidationException::withMessages(['category' => 'Category is fixed after stock history exists. It cannot be changed to bypass expiry tracking.']);
            $supply->update($data);
        }, 5);
        if ($request->input('_from') === 'index') {
            return redirect()->route('supplies.index')->with('success', 'Supply updated.');
        }
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
    public function show(Request $request, Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $supply->load('stockEntries');
        $baseline = DB::table('inventory_baselines')->where('supply_id', $supply->id)->first();
        $netMovement = (float) $supply->inventoryTransactions()->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'stock_out' THEN -quantity ELSE quantity END), 0) AS net")->value('net');
        $movements = $supply->inventoryTransactions()->with(['user', 'operation','allocations.entry'])->latest('id')->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            $request->validate(['stock_entry_id'=>['nullable','integer']]);
            $selectedEntry = $request->filled('stock_entry_id') ? $supply->stockEntries->firstWhere('id',$request->integer('stock_entry_id')) : null;
            abort_if($request->filled('stock_entry_id') && !$selectedEntry,404);
            return response()->json([
                'supply' => [
                    'id' => $supply->id,
                    'supply_name' => $supply->supply_name,
                    'category' => $supply->category,
                    'unit' => $supply->unit,
                    'current_quantity' => (float) $supply->current_quantity,
                    'formatted_quantity' => number_format($supply->current_quantity, 2),
                    'reorder_level' => (float) $supply->reorder_level,
                    'formatted_reorder_level' => number_format($supply->reorder_level, 2),
                    'is_active' => (bool) $supply->is_active,
                    'is_low_stock' => (bool) $supply->is_low_stock,
                    'is_out_of_stock' => $supply->usable_quantity == 0,
                    'usable_quantity' => $supply->usable_quantity,
                    'formatted_usable_quantity' => number_format($supply->usable_quantity, 2),
                    'created_at' => $supply->created_at?->format('M d, Y'),
                ],
                'baseline' => $baseline ? [
                    'opening_quantity' => (float) $baseline->opening_quantity,
                    'formatted_opening_quantity' => number_format($baseline->opening_quantity, 2),
                    'unit' => $baseline->unit,
                    'source' => $baseline->source,
                    'source_label' => $baseline->source === 'legacy_reconciliation' ? 'Legacy reconciliation baseline' : 'Opening balance',
                    'established_at' => \Carbon\Carbon::parse($baseline->established_at)->format('M d, Y'),
                ] : null,
                'net_movement' => $netMovement,
                'formatted_net_movement' => number_format($netMovement, 2),
                'entries' => $this->supplyData($supply)['entries'],
                'selected_entry' => $selectedEntry ? ['stock_entry_id'=>$selectedEntry->id,'opening_quantity'=>(float)$selectedEntry->opening_quantity,'remaining_quantity'=>(float)$selectedEntry->remaining_quantity,'expiry_date'=>$selectedEntry->expiry_date?->toDateString(),'stock_in_date'=>$selectedEntry->stock_in_date?->toDateString(),'source'=>$selectedEntry->source] : null,
                'entry_history' => $selectedEntry ? $selectedEntry->allocations()->with('movement.operation')->latest('id')->limit(20)->get()->map(fn($a)=>['id'=>$a->id,'quantity'=>(float)$a->quantity,'type'=>$a->movement->operation?->type ?? 'Movement','date'=>$a->movement->transaction_date->format('M j, Y')])->all() : [],
                'movements' => $movements->take(5)->map(fn ($m) => [
                    'id' => $m->id,
                    'operation_type' => $m->operation?->type ?? 'manual',
                    'transaction_type' => $m->transaction_type,
                    'quantity' => (float) $m->quantity,
                    'formatted_quantity' => ($m->transaction_type === 'stock_out' ? '-' : '+') . number_format($m->quantity, 2),
                    'before_quantity' => number_format($m->before_quantity, 2),
                    'after_quantity' => number_format($m->after_quantity, 2),
                    'user_name' => $m->user ? ($m->user->first_name . ' ' . $m->user->last_name) : 'Staff',
                    'date' => $m->created_at?->format('M d, Y h:i A'),
                    'notes' => $m->notes,
                ])->values(),
                'routes' => [
                    'edit' => route('supplies.edit', $supply),
                    'history' => route('inventory.history', ['supply_id' => $supply->id]),
                    'stock_in' => route('inventory.create', ['type' => 'receipt', 'supply_id' => $supply->id]),
                    'stock_out' => route('inventory.create', ['type' => 'usage', 'supply_id' => $supply->id]),
                ],
            ]);
        }

        return view('admin.supplies.show', compact('supply', 'baseline', 'netMovement', 'movements'));
    }
    public function lookup(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $today = InventoryCalendar::date();
        return Supply::active()->withUsableQuantity()->with('stockEntries')
            ->where('supply_name', 'like', '%'.$request->string('q').'%')
            ->orderByRaw("
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM stock_entries se
                        WHERE se.supply_id = supplies.id
                          AND se.remaining_quantity > 0
                          AND se.expiry_date >= ?
                    ) THEN 0
                    ELSE 1
                END ASC
            ", [$today])
            ->orderByRaw("
                (SELECT MIN(se.expiry_date)
                 FROM stock_entries se
                 WHERE se.supply_id = supplies.id
                   AND se.remaining_quantity > 0
                   AND se.expiry_date >= ?) ASC
            ", [$today])
            ->orderBy('supply_name')
            ->limit(20)
            ->get()
            ->map(fn ($supply) => $this->supplyData($supply));
    }
    private function supplyData(Supply $supply): array
    {
        $supply->loadMissing('stockEntries');
        return ['id' => $supply->id, 'supply_name' => $supply->supply_name, 'category' => $supply->category, 'unit' => $supply->unit,
            'current_quantity' => $supply->current_quantity, 'usable_quantity' => $supply->usable_quantity, 'stock_version' => (int)$supply->stock_version,
            'entries' => $supply->stockEntries->where('remaining_quantity','>',0)->map(function ($entry) use ($supply) {
                $entry->setRelation('supply', $supply);
                return ['stock_entry_id' => $entry->id, 'source' => $entry->source, 'stock_in_date' => $entry->stock_in_date?->toDateString(), 'expiry_date' => $entry->expiry_date?->toDateString(), 'remaining_quantity' => (float)$entry->remaining_quantity, 'status' => $entry->expiry_status];
            })->values()->all()];
    }
    public function preview(Request $request)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['lines' => ['required','array','min:1','max:100'], 'lines.*.supply_id' => ['required','integer','distinct','exists:supplies,id'], 'lines.*.quantity' => ['required','numeric','decimal:0,2','between:0.01,99999999.99'], 'lines.*.stock_entry_id' => ['prohibited'], 'lines.*.entries' => ['prohibited'], 'lines.*.allocations' => ['prohibited']]);
        return response()->json(app(StockEntryLedger::class)->preview($data['lines']));
    }
    public function verifyForm(Request $request)
    {
        StaffAccess::requireOwner($request->user());
        $supplies = Supply::where('category','ingredients')->with(['stockEntries' => fn ($q) => $q->whereNull('expiry_date')->where('remaining_quantity','>',0)->whereIn('source',['opening_stock','legacy_reconciliation'])])->whereHas('stockEntries', fn ($q) => $q->whereNull('expiry_date')->where('remaining_quantity','>',0)->whereIn('source',['opening_stock','legacy_reconciliation']))->orderBy('supply_name')->get();
        return view('admin.supplies.verify-opening',compact('supplies'));
    }
    public function verifyOpening(Request $request)
    {
        StaffAccess::requireOwner($request->user());
        $data = $request->all(); $data['type'] = 'expiry_verification'; $data['operation_date'] = InventoryCalendar::date();
        $operation = $this->inventoryService->post($data, $request->user());
        return redirect()->route('inventory.show', $operation)->with('success','Opening stock verified. On-hand quantities and prior history were preserved.');
    }
    public function operationForm(Request $request, string $type)
    {
        Gate::authorize('manage-inventory');
        abort_unless(in_array($type, ['receipt', 'usage', 'waste', 'stocktake']), 404);
        $type = $request->old('type', $type);
        $lines = $request->old('lines', []);
        if (!$request->session()->hasOldInput() && $request->filled('supply_id')) {
            $request->validate(['supply_id' => ['integer']]);
            $supply = Supply::active()->findOrFail($request->integer('supply_id'));
            $lines = [['supply_id' => $supply->id, 'quantity' => '']];
        }
        $selected = Supply::whereIn('id', array_slice(array_column($lines, 'supply_id'), 0, 100))->get()->keyBy('id');
        $initialLines = array_map(function ($line) use ($selected) {
            $supply = $selected->get($line['supply_id'] ?? null);
            $saved = collect($line['entries'] ?? [])->keyBy('stock_entry_id');
            $info = $supply ? $this->supplyData($supply) : ['entries'=>[], 'usable_quantity'=>0];
            return array_merge($line, ['name' => $supply?->supply_name ?? 'Unavailable supply', 'category'=>$supply?->category, 'unit' => $supply?->unit,
                'current_quantity' => $supply?->current_quantity, 'usable_quantity'=>$info['usable_quantity'], 'expected_version' => $line['expected_version'] ?? $supply?->stock_version,
                'entries'=>array_map(fn ($entry) => $entry + ['quantity'=>$saved->get($entry['stock_entry_id'])['quantity'] ?? ''], $info['entries'])]);
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
        $operation->load(['user', 'movements.supply.stockEntries', 'movements.user', 'movements.operation', 'movements.allocations.entry', 'reversal', 'original']);
        return view('admin.supplies.operation', compact('operation'));
    }
    public function reverse(Request $request, InventoryOperation $operation)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['required', 'uuid'], 'notes' => ['required', 'string', 'max:2000']]);
        $reversal = $this->inventoryService->reverse($operation, $data['submission_key'], $data['notes'], $request->user(), $request->input('reconciliation', []));
        return redirect()->route('inventory.show', $reversal)->with('success', 'Linked reversal posted. Create a new operation with the corrected quantities if needed.');
    }
    public function history(Request $request)
    {
        Gate::authorize('manage-inventory');
        $request->validate(['q' => ['nullable', 'string', 'max:255'], 'type' => ['nullable', 'in:receipt,usage,waste,stocktake,adjustment,reversal,expiry_verification'], 'supply_id' => ['nullable', 'integer'], 'stock_entry_id'=>['nullable','integer']]);
        $query = InventoryTransaction::with(['supply', 'user', 'operation','allocations.entry']);
        if ($request->filled('q')) $query->whereHas('supply', fn ($q) => $q->where('supply_name', 'like', '%'.$request->string('q').'%'));
        if ($request->filled('type')) $query->whereHas('operation', fn ($q) => $q->where('type', $request->type));
        if ($request->filled('supply_id')) $query->where('supply_id', $request->supply_id);
        if ($request->filled('stock_entry_id')) $query->whereHas('allocations',fn ($q)=>$q->where('stock_entry_id',$request->stock_entry_id));
        return view('admin.supplies.history', ['movements' => $query->latest('id')->paginate(25)->withQueryString()]);
    }
    public function legacyMovement(InventoryTransaction $movement)
    {
        Gate::authorize('manage-inventory');
        if ($movement->inventory_operation_id) return redirect()->route('inventory.show', $movement->inventory_operation_id);
        $movement->load(['supply.stockEntries', 'user', 'operation', 'allocations.entry','reversal.operation']);
        return view('admin.supplies.legacy-movement', compact('movement'));
    }
    public function reverseLegacy(Request $request, InventoryTransaction $movement)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['required', 'uuid'], 'notes' => ['required', 'string', 'max:2000']]);
        $reversal = $this->inventoryService->reverseLegacy($movement, $data['submission_key'], $data['notes'], $request->user(), $request->input('reconciliation', []));
        return redirect()->route('inventory.show', $reversal)->with('success', 'Linked reversal posted. The original legacy movement is preserved.');
    }
    public function recordTransaction(Request $request, Supply $supply)
    {
        Gate::authorize('manage-inventory');
        $data = $request->validate(['submission_key' => ['nullable', 'uuid'], 'transaction_type' => ['required', 'in:stock_in,stock_out,adjustment'], 'quantity' => ['required', 'numeric'], 'notes' => ['required_if:transaction_type,adjustment', 'nullable', 'string', 'max:2000']]);
        $entryData = $request->validate(['stock_entry_id'=>['nullable','integer'], 'allocations'=>['nullable','array'], 'expiry_date'=>['nullable','date_format:Y-m-d'],'entries'=>['nullable','array'],'entries.*.stock_entry_id'=>['required','integer'],'entries.*.quantity'=>['required','numeric','decimal:0,2','between:0,99999999.99']]);
        $this->inventoryService->recordTransaction($supply, $data['transaction_type'], (float) $data['quantity'], $request->user(), $data['notes'] ?? null, null, $data['submission_key'] ?? null, $entryData);
        return back()->with('success', 'Movement recorded.');
    }
}
