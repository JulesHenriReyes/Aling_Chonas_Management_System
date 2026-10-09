@extends('layouts.admin')
@section('title', 'Inventory & supplies')
@section('content')
<div class="workspace inventory-workspace" x-data="{
    showCreateModal: {{ $errors->any() && old('_form') !== 'edit_supply' ? 'true' : 'false' }},
    showEditModal: {{ $errors->any() && old('_form') === 'edit_supply' ? 'true' : 'false' }},
    editSupply: {
        id: {{ old('_form') === 'edit_supply' ? old('supply_id', 'null') : 'null' }},
        supply_name: @js(old('_form') === 'edit_supply' ? old('supply_name', '') : ''),
        category: @js(old('_form') === 'edit_supply' ? old('category', 'ingredients') : 'ingredients'),
        unit: @js(old('_form') === 'edit_supply' ? old('unit', 'kg') : 'kg'),
        reorder_level: @js(old('_form') === 'edit_supply' ? old('reorder_level', '0.00') : '0.00'),
        is_active: {{ old('_form') === 'edit_supply' ? (old('is_active', true) ? 'true' : 'false') : 'true' }},
        update_url: @js(old('_form') === 'edit_supply' && old('supply_id') ? route('supplies.update', old('supply_id')) : '')
    },
    openEditSupply(supply) {
        this.editSupply = {
            id: supply.id,
            supply_name: supply.supply_name,
            category: supply.category,
            unit: supply.unit,
            reorder_level: Number(supply.reorder_level ?? 0).toFixed(2),
            is_active: Boolean(supply.is_active),
            update_url: '/supplies/' + supply.id
        };
        this.showEditModal = true;
    },
    showDetailDrawer: false,
    detailLoading: false,
    detailSequence: 0,
    detailError: '',
    drawerSupply: null,
    drawerEntry: null,
    drawerEntryHistory: [],
    drawerBaseline: null,
    drawerMovements: [],
    drawerNetMovement: 0,
    openSupplyDetail(supply) {
        const request = ++this.detailSequence;
        this.detailError = '';
        this.drawerSupply = supply;
        this.drawerEntry = supply.entry || null;
        this.drawerEntryHistory = [];
        this.showDetailDrawer = true;
        this.detailLoading = true;
        this.drawerBaseline = null;
        this.drawerMovements = [];
        this.drawerNetMovement = 0;

        fetch('/supplies/' + supply.id + (supply.entry ? '?stock_entry_id=' + supply.entry.stock_entry_id : ''), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to load details');
            return res.json();
        })
        .then(data => {
            if (request === this.detailSequence && this.showDetailDrawer && this.drawerSupply && this.drawerSupply.id === supply.id) {
                this.drawerSupply = { ...this.drawerSupply, ...data.supply, ...data.routes, entries: data.entries };
                this.drawerBaseline = data.baseline;
                this.drawerEntry = data.selected_entry || this.drawerEntry;
                this.drawerEntryHistory = data.entry_history || [];
                this.drawerMovements = data.movements || [];
                this.drawerNetMovement = data.net_movement || 0;
                this.detailLoading = false;
            }
        })
        .catch(() => {
            if (request === this.detailSequence) { this.detailLoading = false; this.detailError = 'Could not load entry details. Close this panel and open it again to retry.'; }
        });
    },
    closeDetailDrawer() {
        this.showDetailDrawer = false;
        this.detailSequence++;
    },
    selectEntry(entry) {
        if (!this.drawerSupply || !entry) return;
        this.drawerEntry = { ...entry };
        this.drawerEntryHistory = [];
        fetch('/supplies/' + this.drawerSupply.id + '?stock_entry_id=' + entry.stock_entry_id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.ok ? res.json() : null)
        .then(data => {
            if (data && this.drawerEntry && this.drawerEntry.stock_entry_id === entry.stock_entry_id) {
                this.drawerEntry = data.selected_entry || this.drawerEntry;
                this.drawerEntryHistory = data.entry_history || [];
            }
        })
        .catch(() => {});
    },
    formatDate(d) {
        if (!d) return 'None';
        const parts = String(d).split('-');
        if (parts.length !== 3) return d;
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const m = months[parseInt(parts[1], 10) - 1] || parts[1];
        return `${m} ${parseInt(parts[2], 10)}, ${parts[0]}`;
    },
    formatSource(s) {
        if (!s) return 'Stock in';
        const map = {
            'opening_stock': 'Opening Stock',
            'verified_opening': 'Verified Opening',
            'receipt': 'Purchase Receipt',
            'manual': 'Manual Stock-in',
            'adjustment': 'Adjustment',
            'legacy_reconciliation': 'Reconciliation Baseline'
        };
        return map[s] || s.replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    },
    formatActivityType(t) {
        if (!t) return 'Movement';
        const map = {
            'expiry_verification': 'Expiry Verification',
            'usage': 'Used in Baking',
            'waste': 'Waste / Spoilage',
            'receipt': 'Stock Receipt',
            'adjustment': 'Stock Adjustment',
            'stocktake': 'Stock Count'
        };
        return map[t] || t.replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    },
    getBatchStatus(entry) {
        if (!entry) return null;
        if (this.drawerSupply && this.drawerSupply.category === 'packaging') {
            return { label: 'Packaging', class: 'bg-cocoa-50 text-cocoa-600 border-cocoa-200' };
        }
        if (!entry.expiry_date) {
            return { label: 'No expiry set', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        }
        const today = new Date();
        today.setHours(0,0,0,0);
        const expDateStr = String(entry.expiry_date).split('T')[0];
        const exp = new Date(expDateStr + 'T00:00:00');
        const diffDays = Math.ceil((exp - today) / (1000 * 60 * 60 * 24));
        if (diffDays < 0) {
            return { label: 'Expired', class: 'bg-rose-50 text-rose-700 border-rose-200' };
        }
        if (diffDays <= 7) {
            return { label: 'Expiring soon', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        }
        return { label: 'Usable', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
}">
    <header class="workspace-heading">
        <div>
            <h1>Inventory & supplies</h1>
            <p>
                Shared ingredients and packaging, across every package.
                <span class="text-cocoa-300 mx-1.5" aria-hidden="true">&middot;</span>
                <a class="ui-button quiet text-xs font-semibold" href="{{ route('inventory.history') }}">Movement history</a>
            </p>
        </div>
        <div class="workspace-actions">
            <button type="button" class="ui-button" @click="showCreateModal = true"><x-icon name="plus" /> Add supply</button>
            <a class="ui-button" href="{{ route('inventory.create','usage') }}">Stock out</a>
            <a class="ui-button primary" href="{{ route('inventory.create','receipt') }}"><x-icon name="plus" /> Stock in</a>
        </div>
    </header>

    @if(array_sum($expiryCounts))
        <nav class="inventory-expiry-summary" aria-label="Expiry alerts">
            @foreach(['expiring'=>'expiring soon','expired'=>'expired','unknown'=>'with unknown expiry'] as $state=>$label)
                @if($expiryCounts[$state])<a href="{{ route('supplies.index',['expiry'=>$state]) }}">{{ $expiryCounts[$state] }} {{ $label }}</a>@endif
            @endforeach
            @if($expiryCounts['unknown'] && auth()->user()->isOwner())<a class="inventory-review-link" href="{{ route('inventory.verify') }}">Review opening stock →</a>@endif
        </nav>
        @if($expiryCounts['unknown'])<p class="form-hint">Existing ingredients with unknown expiry are held from baking. Verify their dates instead of stocking in the same quantities again.</p>@endif
    @endif

    <form method="GET" class="workspace-filters" aria-label="Filter supplies">
        <div class="filter-search">
            <label for="supply-q">Search</label>
            <input id="supply-q" name="q" value="{{ request('q') }}" placeholder="Supply name">
        </div>
        <div>
            <label for="supply-category">Category</label>
            <select id="supply-category" name="category">
                <option value="">All categories</option>
                @foreach(['ingredients','packaging'] as $category)
                    <option value="{{ $category }}" @selected(request('category')===$category)>{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="supply-status">Stock status</label>
            <select id="supply-status" name="status">
                <option value="">All stock levels</option>
                @foreach(['healthy'=>'In stock','low'=>'Low stock','out'=>'Out of stock'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="supply-active">Activity</label>
            <select id="supply-active" name="active">
                @foreach(['active'=>'Active','inactive'=>'Inactive','all'=>'All supplies'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('active','active')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="supply-expiry">Expiration</label><select id="supply-expiry" name="expiry"><option value="">All expirations</option>@foreach(['available'=>'Usable','expiring'=>'Expiring soon (within 7 days)','expired'=>'Expired','unknown'=>'Expiry unknown'] as $value=>$label)<option value="{{ $value }}" @selected(request('expiry')===$value)>{{ $label }}</option>@endforeach</select></div>
        <input type="hidden" name="sort" value="{{ request('sort','expiry') }}">
        <input type="hidden" name="direction" value="{{ request('direction','asc') }}">
        <button class="ui-button">Apply</button>
        <a class="ui-button quiet" href="{{ route('supplies.index') }}">Reset</a>
    </form>

    <div class="workspace-table table-scroll" role="region" aria-label="Supplies table" tabindex="0">
        <table class="supplies-table inventory-entry-table">
            <colgroup><col><col class="inventory-quantity-col"><col class="inventory-expiry-col"><col class="inventory-status-col"><col class="supplies-actions-col"></colgroup>
            <thead><tr><th><x-sort-heading column="supply_name" label="Supply" /></th><th>Remaining</th><th><x-sort-heading column="expiry" label="Expiration" /></th><th>Status <x-tooltip text="Availability uses combined usable stock. Expired and unknown ingredient entries stay on hand." /></th><th scope="col">Actions</th></tr></thead>
            <tbody>
                @forelse($supplies as $supply)
                    @php
                        $first = app(\App\Services\StockEntryLedger::class)->ordered($supply, $supply->stockEntries)->first()?->id;
                        $displayEntries = $supply->category === 'ingredients' ? $supply->stockEntries : collect([null]);
                        if(request('expiry')) $displayEntries = $displayEntries->filter(function($e) use($supply) { if(!$e) return false; $e->setRelation('supply',$supply); return request('expiry') === 'available' ? in_array($e->expiry_status,['available','expiring']) : $e->expiry_status === request('expiry'); });
                        if($displayEntries->isEmpty() && !request('expiry')) $displayEntries = collect([null]);
                    @endphp
                    @foreach($displayEntries as $entry)
                        @php
                            if($entry) $entry->setRelation('supply',$supply);
                            $state = $entry?->expiry_status;
                            $entryData = $entry ? ['stock_entry_id'=>$entry->id, 'opening_quantity'=>(float)$entry->opening_quantity, 'remaining_quantity'=>(float)$entry->remaining_quantity, 'expiry_date'=>$entry->expiry_date?->toDateString(), 'stock_in_date'=>$entry->stock_in_date?->toDateString(), 'source'=>$entry->source, 'status'=>$state] : null;
                            $supplyData = ['id'=>$supply->id,'supply_name'=>$supply->supply_name,'category'=>$supply->category,'unit'=>$supply->unit,'current_quantity'=>(float)$supply->current_quantity,'formatted_quantity'=>number_format($supply->current_quantity,2),'usable_quantity'=>$supply->usable_quantity,'formatted_usable_quantity'=>number_format($supply->usable_quantity,2),'reorder_level'=>(float)$supply->reorder_level,'formatted_reorder_level'=>number_format($supply->reorder_level,2),'is_active'=>(bool)$supply->is_active,'is_low_stock'=>(bool)$supply->is_low_stock,'is_out_of_stock'=>$supply->usable_quantity==0,'edit_url'=>route('supplies.edit',$supply),'history_url'=>route('inventory.history',['supply_id'=>$supply->id]),'stock_in_url'=>route('inventory.create',['type'=>'receipt','supply_id'=>$supply->id]),'stock_out_url'=>route('inventory.create',['type'=>'usage','supply_id'=>$supply->id]),'entry'=>$entryData];
                        @endphp
                        <tr @click="openSupplyDetail(@js($supplyData))" class="inventory-entry-row">
                            <td><a class="record-link inventory-supply-link" href="{{ route('supplies.show',$supply) }}" @click.stop="if (!$event.ctrlKey && !$event.metaKey && $event.button === 0) { $event.preventDefault(); openSupplyDetail(@js($supplyData)); }">
                                    <span class="inventory-supply-name">{{ $supply->supply_name }}</span>
                                    <span class="inventory-row-meta">{{ ucfirst($supply->category) }}@if($entry) · {{ $entry->stock_in_date ? 'Stocked '.$entry->stock_in_date->format('M j, Y') : 'Opening stock' }}@endif</span>
                                </a>
                                @unless($supply->is_active)<span class="status">Inactive</span>@endunless
                            </td>
                            <td>{{ number_format($entry?->remaining_quantity ?? $supply->current_quantity,2) }} {{ $supply->unit }}<span class="inventory-row-meta">{{ number_format($supply->usable_quantity,2) }} {{ $supply->unit }} usable total</span></td>
                            <td>{{ $entry?->expiry_date?->format('M j, Y') ?? ($entry ? 'Unknown' : ($supply->category === 'packaging' ? 'Not applicable' : '—')) }}
                                @if($supply->category === 'ingredients' && $entry && $entry->id === $first && $entry->expiry_date)<span class="inventory-row-meta inventory-use-first">Use first</span>@endif
                            </td>
                            <td>
                                @if($entry)<x-status :value="['unknown'=>'unknown_expiry','expired'=>'expired','expiring'=>'expiring_soon','available'=>'available'][$state]" :label="['unknown'=>'Expiry unknown','expired'=>'Expired','expiring'=>'Expiring soon','available'=>'Available'][$state]" />
                                @else<x-status :value="$supply->usable_quantity == 0 ? 'out_of_stock' : ($supply->is_low_stock ? 'low_stock' : 'in_stock')" :label="$supply->usable_quantity == 0 ? 'Out of stock' : ($supply->is_low_stock ? 'Low stock' : 'Available')" />@endif
                            </td>
                            <td @click.stop><details class="row-actions supply-actions"><summary aria-label="Options for {{ $supply->supply_name }}{{ $entry ? ' entry '.$entry->id : '' }}"><x-icon name="dots-vertical" /></summary><div>
                                <a href="{{ route('supplies.show',$supply) }}" @click.prevent="openSupplyDetail(@js($supplyData)); $el.closest('details').removeAttribute('open')">Details</a>
                                <a href="{{ route('inventory.create',['type'=>'usage','supply_id'=>$supply->id]) }}">Stock out</a>
                                <a href="{{ route('inventory.history',['supply_id'=>$supply->id,'stock_entry_id'=>$entry?->id]) }}">History</a>
                                <a href="{{ route('supplies.edit',$supply) }}" @click.prevent="openEditSupply(@js($supplyData)); $el.closest('details')?.removeAttribute('open')">Edit supply</a>
                            </div></details></td>
                        </tr>
                    @endforeach
                @empty
                    <tr style="height: 100%;">
                        <td colspan="5" class="empty-state text-center" style="text-align: center !important; vertical-align: middle !important; padding: 4rem 1rem !important;">
                            @if(request('expiry') === 'expiring')
                                <div style="font-weight: 600; font-size: 1rem; color: var(--color-cocoa-900, #2c1a11); margin-bottom: 0.25rem;">No ingredients are expiring soon</div>
                                <div style="color: var(--color-cocoa-600, #7a5d4d); margin-bottom: 1rem; font-size: 0.875rem;">None of your active ingredient batches expire within the next 7 days.</div>
                                <a class="ui-button quiet" href="{{ route('supplies.index') }}">Clear filter</a>
                            @elseif(request('expiry') === 'expired')
                                <div style="font-weight: 600; font-size: 1rem; color: var(--color-cocoa-900, #2c1a11); margin-bottom: 0.25rem;">No ingredients are expired</div>
                                <div style="color: var(--color-cocoa-600, #7a5d4d); margin-bottom: 1rem; font-size: 0.875rem;">All active ingredient batches are currently within their expiration dates.</div>
                                <a class="ui-button quiet" href="{{ route('supplies.index') }}">Clear filter</a>
                            @elseif(request('expiry') === 'unknown')
                                <div style="font-weight: 600; font-size: 1rem; color: var(--color-cocoa-900, #2c1a11); margin-bottom: 0.25rem;">No ingredients with unknown expiry</div>
                                <div style="color: var(--color-cocoa-600, #7a5d4d); margin-bottom: 1rem; font-size: 0.875rem;">All active ingredient batches have recorded expiration dates.</div>
                                <a class="ui-button quiet" href="{{ route('supplies.index') }}">Clear filter</a>
                            @else
                                No supplies match these filters. <a href="{{ route('supplies.index') }}">Clear filters</a> or add a supply.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-workspace-pagination :records="$supplies" />

    <!-- Add Supply Popup Modal -->
    <div x-show="showCreateModal"
         x-cloak
         data-dialog
         role="dialog"
         aria-modal="true"
         aria-labelledby="supply-modal-title"
         tabindex="-1"
         @keydown.escape.window="showCreateModal = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-lg w-full p-6 shadow-xl"
             x-show="showCreateModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
             @click.away="showCreateModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-cocoa-100/60 mb-4">
                <div>
                    <h2 id="supply-modal-title" class="text-base font-bold text-cocoa-600">Add New Supply</h2>
                    <p class="text-xs text-cocoa-400 mt-0.5">Shared ingredients or packaging items across every package.</p>
                </div>
                <button type="button"
                        data-dialog-close
                        @click="showCreateModal = false"
                        aria-label="Close dialog"
                        class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition">
                    <x-icon name="close" class="w-5 h-5" />
                </button>
            </div>

            <form action="{{ route('supplies.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="current_quantity" value="0">

                <div>
                    <label for="modal-supply-name" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Supply name <span class="text-red-500">*</span></label>
                    <input id="modal-supply-name"
                           name="supply_name"
                           type="text"
                           required
                           maxlength="255"
                           placeholder="e.g. All-purpose flour, Cocoa powder, Cake box 8x8"
                           value="{{ old('supply_name') }}"
                           class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                    @error('supply_name')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="modal-category" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select id="modal-category"
                                name="category"
                                required
                                class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                            @foreach(['ingredients' => 'Ingredients', 'packaging' => 'Packaging'] as $val => $catLabel)
                                <option value="{{ $val }}" @selected(old('category', 'ingredients') === $val)>{{ $catLabel }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    @php
                        $standardUnits = [
                            'kg' => 'kg (Kilogram)',
                            'g' => 'g (Gram)',
                            'piece' => 'piece (Individual piece)',
                            'pcs' => 'pcs (Pieces)',
                            'can' => 'can (Can)',
                            'ml' => 'ml (Milliliter)',
                            'litre' => 'litre (Liter)',
                            'pack' => 'pack (Pack)',
                            'box' => 'box (Box)',
                            'bottle' => 'bottle (Bottle)',
                        ];
                        $oldUnit = old('unit', 'kg');
                        $isCustomUnit = !empty($oldUnit) && !array_key_exists($oldUnit, $standardUnits);
                        $initialUnitMode = $isCustomUnit ? 'custom' : $oldUnit;
                    @endphp
                    <div x-data="{
                        unitMode: '{{ $initialUnitMode }}',
                        customUnit: '{{ $isCustomUnit ? addslashes($oldUnit) : '' }}'
                    }">
                        <label for="modal-unit" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Stock unit <span class="text-red-500">*</span></label>
                        <select id="modal-unit"
                                x-model="unitMode"
                                :name="unitMode === 'custom' ? null : 'unit'"
                                name="unit"
                                required
                                class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                            @foreach($standardUnits as $uVal => $uLabel)
                                <option value="{{ $uVal }}" @selected($oldUnit === $uVal)>{{ $uLabel }}</option>
                            @endforeach
                            <option value="custom" @selected($isCustomUnit)>Other (custom unit)...</option>
                        </select>
                        <div x-show="unitMode === 'custom'" x-cloak class="mt-2">
                            <input type="text"
                                   x-model="customUnit"
                                   :name="unitMode === 'custom' ? 'unit' : null"
                                   :required="unitMode === 'custom'"
                                   maxlength="50"
                                   placeholder="Enter custom unit (e.g. tub, roll, sheet)"
                                   class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                        </div>
                        <p class="text-[11px] text-cocoa-400 mt-1">Eggs: piece; milk: can; flour: kg.</p>
                        @error('unit')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="modal-reorder-level" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Reorder alert threshold <span class="text-red-500">*</span></label>
                    <input id="modal-reorder-level"
                           name="reorder_level"
                           type="number"
                           min="0"
                           step="0.01"
                           max="99999999.99"
                           required
                           value="{{ old('reorder_level', '0.00') }}"
                           class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                    <p class="text-[11px] text-cocoa-400 mt-1">Triggers "Low stock" warning when usable quantity falls to or below this level.</p>
                    @error('reorder_level')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-1">
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-cocoa-600">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               @checked(old('is_active', true))
                               class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                        <span>Active supply (available for recipes and stock movements)</span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2.5 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button"
                            data-dialog-close
                            @click="showCreateModal = false"
                            class="ui-button quiet">
                        Cancel
                    </button>
                    <button type="submit"
                            name="next"
                            value="stock_in"
                            class="ui-button primary">
                        Save & stock in
                    </button>
                    <button type="submit"
                            class="ui-button">
                        Save supply only
                    </button>
                </div>
            </form>
        </div>
    </div>

    @include('admin.supplies.edit-modal', ['fromIndex' => true])

    <!-- Sliding Detail Drawer from Right (Anchored strictly below the header in all zoom/size states) -->
    <div x-show="showDetailDrawer"
         x-cloak
         data-detail-drawer
         data-dialog
         @keydown.escape.window="closeDetailDrawer"
         class="fixed left-0 right-0 bottom-0 z-30 overflow-hidden"
         style="top: var(--topbar-height, 3.5rem); height: calc(100dvh - var(--topbar-height, 3.5rem)); display: none;">

        <!-- Backdrop scrim: strictly below the header, clean dark scrim WITHOUT backdrop-blur -->
        <div x-show="showDetailDrawer"
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeDetailDrawer"
             class="absolute inset-0 bg-black/25"></div>

        <!-- Sliding Panel Container from Right -->
        <div class="absolute inset-y-0 right-0 max-w-full flex pl-8 pointer-events-none">
            <div x-show="showDetailDrawer"
                 x-transition:enter="transform transition ease-out duration-250"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 @click.away="closeDetailDrawer"
                 class="w-screen max-w-md sm:max-w-[420px] bg-white border-l border-cocoa-100 shadow-2xl flex flex-col h-full pointer-events-auto"
                 role="dialog"
                 aria-modal="true"
                 aria-label="Supply details">

                <template x-if="drawerSupply">
                    <div class="flex flex-col h-full overflow-hidden">
                        <!-- Drawer Header -->
                        <div class="px-5 py-4 border-b border-cocoa-100 flex items-start justify-between gap-3 bg-cream-50/60">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-cocoa-500 bg-white px-2 py-0.5 rounded border border-cocoa-100"
                                          x-text="drawerSupply.category === 'ingredients' ? 'Ingredient' : 'Packaging'"></span>
                                    <template x-if="drawerSupply.is_active">
                                        <span class="inline-flex items-center text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Active</span>
                                    </template>
                                    <template x-if="!drawerSupply.is_active">
                                        <span class="inline-flex items-center text-[10px] font-semibold text-stone-500 bg-stone-100 px-2 py-0.5 rounded border border-stone-200">Inactive</span>
                                    </template>
                                </div>
                                <h2 class="text-base font-bold text-cocoa-700 break-words" x-text="drawerSupply.supply_name"></h2>
                            </div>
                            <button type="button"
                                    @click="closeDetailDrawer"
                                    class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition focus:outline-none shrink-0"
                                    aria-label="Close detail panel" data-dialog-close>
                                <x-icon name="close" class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Drawer Body (Scrollable) -->
                        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-sm">
                            <!-- Stock Quantity & Status Card -->
                            <div class="bg-cream-50/70 border border-cocoa-100 rounded-xl p-4 space-y-3">
                                <div class="flex items-baseline justify-between gap-2">
                                    <div>
                                        <span class="text-[11px] font-semibold text-cocoa-400 uppercase tracking-wider block">Current On Hand</span>
                                        <div class="flex items-baseline gap-1.5 mt-0.5">
                                            <span class="text-2xl font-extrabold text-cocoa-700" x-text="drawerSupply.formatted_quantity"></span>
                                            <span class="text-sm font-semibold text-cocoa-500" x-text="drawerSupply.unit"></span>
                                        </div>
                                    </div>
                                    <div>
                                        <template x-if="drawerSupply.is_out_of_stock">
                                            <span class="inline-flex items-center text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded-full">Out of stock</span>
                                        </template>
                                        <template x-if="!drawerSupply.is_out_of_stock && drawerSupply.is_low_stock">
                                            <span class="inline-flex items-center text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full">Low stock</span>
                                        </template>
                                        <template x-if="!drawerSupply.is_out_of_stock && !drawerSupply.is_low_stock">
                                            <span class="inline-flex items-center text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">In stock</span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Stock Threshold Guidance -->
                                <div class="pt-2.5 border-t border-cocoa-100/70 grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="text-cocoa-400 block font-medium">Reorder Alert Level</span>
                                        <span class="font-bold text-cocoa-700" x-text="drawerSupply.formatted_reorder_level + ' ' + drawerSupply.unit"></span>
                                    </div>
                                    <div>
                                        <span class="text-cocoa-400 block font-medium">Stock Condition</span>
                                        <span class="font-bold text-cocoa-700" x-text="drawerSupply.is_out_of_stock ? 'Depleted' : (drawerSupply.is_low_stock ? 'Below threshold' : 'Sufficient')"></span>
                                    </div>
                                </div>

                                <!-- Helpful advice alert -->
                                <template x-if="drawerSupply.is_out_of_stock">
                                    <div class="text-xs bg-rose-50 text-rose-800 p-2.5 rounded-lg border border-rose-200/80">
                                        <strong>No usable stock.</strong> Review held entries or stock in verified ingredients to avoid production delays.
                                    </div>
                                </template>
                                <template x-if="!drawerSupply.is_out_of_stock && drawerSupply.is_low_stock">
                                    <div class="text-xs bg-amber-50 text-amber-800 p-2.5 rounded-lg border border-amber-200/80">
                                        <strong>Low stock alert:</strong> Quantity is at or below the reorder threshold (<span x-text="drawerSupply.formatted_reorder_level + ' ' + drawerSupply.unit"></span>).
                                    </div>
                                </template>
                            </div>

                            <!-- Active Batch & Expiry Section -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-semibold text-cocoa-400 uppercase tracking-wider">Active Batch & Expiry</span>
                                    <span class="text-xs font-semibold text-cocoa-700 bg-cream-100 border border-cocoa-100 px-2.5 py-0.5 rounded-full"
                                          x-text="'Usable: ' + (drawerSupply.formatted_usable_quantity || Number(drawerSupply.usable_quantity || 0).toFixed(2)) + ' ' + drawerSupply.unit"></span>
                                </div>

                                <p class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2.5" role="alert" x-show="detailError" x-text="detailError"></p>

                                <!-- Active Batch Card -->
                                <template x-if="drawerEntry">
                                    <div class="bg-white border border-cocoa-100 rounded-xl p-3.5 space-y-3 text-xs shadow-sm">
                                        <!-- Card Header -->
                                        <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-cocoa-100/70">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-sm text-cocoa-800" x-text="'Batch #' + drawerEntry.stock_entry_id"></span>
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-cocoa-50 text-cocoa-600 border border-cocoa-200/50"
                                                      x-text="formatSource(drawerEntry.source)"></span>
                                            </div>
                                            <template x-if="getBatchStatus(drawerEntry)">
                                                <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                      :class="getBatchStatus(drawerEntry).class"
                                                      x-text="getBatchStatus(drawerEntry).label"></span>
                                            </template>
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-2 gap-2.5">
                                            <div>
                                                <span class="text-cocoa-400 block font-medium">Remaining in Batch</span>
                                                <span class="font-bold text-cocoa-800 text-sm" x-text="Number(drawerEntry.remaining_quantity || 0).toFixed(2) + ' ' + drawerSupply.unit"></span>
                                            </div>
                                            <div>
                                                <span class="text-cocoa-400 block font-medium">Expiration Date</span>
                                                <span class="font-bold text-sm"
                                                      :class="drawerEntry.expiry_date ? 'text-cocoa-800' : 'text-cocoa-400 font-normal italic'"
                                                      x-text="drawerEntry.expiry_date ? formatDate(drawerEntry.expiry_date) : (drawerSupply.category === 'packaging' ? 'No expiry' : 'Not recorded')"></span>
                                            </div>
                                            <div>
                                                <span class="text-cocoa-400 block font-medium">Original Quantity</span>
                                                <span class="font-semibold text-cocoa-600" x-text="drawerEntry.opening_quantity ? (Number(drawerEntry.opening_quantity).toFixed(2) + ' ' + drawerSupply.unit) : '—'"></span>
                                            </div>
                                            <div>
                                                <span class="text-cocoa-400 block font-medium">Stock-in Date</span>
                                                <span class="font-semibold text-cocoa-600" x-text="formatDate(drawerEntry.stock_in_date)"></span>
                                            </div>
                                        </div>

                                        <!-- Recent Batch Activity Mini Log -->
                                        <div class="pt-2.5 border-t border-cocoa-100/70 space-y-1.5" x-show="drawerEntryHistory.length > 0">
                                            <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Recent Batch Activity</span>
                                            <div class="space-y-1.5">
                                                <template x-for="activity in drawerEntryHistory" :key="activity.id">
                                                    <div class="flex items-center justify-between text-xs bg-cream-50/50 rounded-lg px-2.5 py-1.5 text-cocoa-600 border border-cocoa-100/60">
                                                        <span class="font-semibold text-cocoa-700" x-text="formatActivityType(activity.type)"></span>
                                                        <div class="flex items-center gap-1.5 text-cocoa-500 text-[11px]">
                                                            <span class="font-bold text-cocoa-700" x-text="Number(activity.quantity).toFixed(2) + ' ' + drawerSupply.unit"></span>
                                                            <span class="text-cocoa-300">·</span>
                                                            <span x-text="activity.date"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Entry History Link -->
                                        <div class="pt-2 border-t border-cocoa-100/70 flex items-center justify-end">
                                            <a class="text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 transition inline-flex items-center gap-1"
                                               :href="drawerSupply.history_url + '&stock_entry_id=' + drawerEntry.stock_entry_id">
                                                <span>Entry history</span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                <!-- Empty State when no batch exists -->
                                <template x-if="!drawerEntry && !detailLoading">
                                    <div class="bg-white border border-cocoa-100 rounded-xl p-4 text-center text-xs text-cocoa-400">
                                        No active batches recorded for this item.
                                    </div>
                                </template>

                                <!-- All Batches List (when multiple batches exist) -->
                                <template x-if="drawerSupply.entries && drawerSupply.entries.length > 1">
                                    <details class="group bg-white border border-cocoa-100 rounded-xl overflow-hidden transition">
                                        <summary class="cursor-pointer select-none px-3.5 py-2.5 text-xs font-semibold text-cocoa-700 flex items-center justify-between hover:bg-cream-50/60 transition list-none">
                                            <div class="flex items-center gap-2">
                                                <span>All Available Batches</span>
                                                <span class="text-[10px] bg-cocoa-100 text-cocoa-700 font-bold px-1.5 py-0.5 rounded-full"
                                                      x-text="drawerSupply.entries.length"></span>
                                            </div>
                                            <svg class="w-4 h-4 text-cocoa-400 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                            </svg>
                                        </summary>
                                        <div class="p-2 border-t border-cocoa-100/70 bg-cream-50/30 space-y-1.5">
                                            <template x-for="entry in drawerSupply.entries" :key="entry.stock_entry_id">
                                                <div @click="selectEntry(entry)"
                                                     class="cursor-pointer rounded-lg p-2.5 text-xs transition border flex items-center justify-between gap-3"
                                                     :class="drawerEntry && drawerEntry.stock_entry_id === entry.stock_entry_id ? 'bg-cream-100/80 border-cocoa-300 ring-1 ring-cocoa-300' : 'bg-white border-cocoa-100 hover:border-cocoa-200 hover:bg-cream-50/50'">
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-bold text-cocoa-800" x-text="'Batch #' + entry.stock_entry_id"></span>
                                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-cocoa-50 text-cocoa-600 border border-cocoa-200/50"
                                                                  x-text="formatSource(entry.source)"></span>
                                                        </div>
                                                        <div class="text-[11px] text-cocoa-400 mt-0.5">
                                                            <span x-text="entry.expiry_date ? ('Expires ' + formatDate(entry.expiry_date)) : (drawerSupply.category === 'packaging' ? 'No expiry' : 'Expiry unknown')"></span>
                                                        </div>
                                                    </div>
                                                    <div class="text-right shrink-0">
                                                        <span class="font-bold text-cocoa-800 block" x-text="Number(entry.remaining_quantity).toFixed(2) + ' ' + drawerSupply.unit"></span>
                                                        <span x-show="drawerEntry && drawerEntry.stock_entry_id === entry.stock_entry_id" class="text-[10px] font-semibold text-cocoa-600">Viewing</span>
                                                        <span x-show="!drawerEntry || drawerEntry.stock_entry_id !== entry.stock_entry_id" class="text-[10px] text-cocoa-400">Click to switch</span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </details>
                                </template>
                            </div>

                            <!-- Action Shortcuts -->
                            <div class="space-y-1.5">
                                <span class="text-[11px] font-semibold text-cocoa-400 uppercase tracking-wider block">Quick Operations</span>
                                <div class="grid grid-cols-3 gap-2">
                                    <a :href="drawerSupply.stock_in_url" class="ui-button primary text-xs justify-center py-2">
                                        <x-icon name="plus" /> Stock in
                                    </a>
                                    <a :href="drawerSupply.stock_out_url" class="ui-button text-xs justify-center py-2">
                                        Stock out
                                    </a>
                                    <a :href="drawerSupply.edit_url" @click.prevent="openEditSupply(drawerSupply)" class="ui-button quiet text-xs justify-center py-2">
                                        Edit
                                    </a>
                                </div>
                            </div>

                            <!-- Baseline & Reconciliation Section -->
                            <div class="space-y-2">
                                <span class="text-[11px] font-semibold text-cocoa-400 uppercase tracking-wider block">Reconciliation Baseline</span>
                                <div class="bg-white border border-cocoa-100 rounded-xl p-3.5 space-y-2.5 text-xs">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <span class="text-cocoa-400 block font-medium">Opening Balance</span>
                                            <span class="font-bold text-cocoa-700" x-text="drawerBaseline ? (drawerBaseline.formatted_opening_quantity + ' ' + drawerBaseline.unit) : 'Not established'"></span>
                                        </div>
                                        <div>
                                            <span class="text-cocoa-400 block font-medium">Net Movements</span>
                                            <span class="font-bold text-cocoa-700" x-text="(drawerNetMovement >= 0 ? '+' : '') + Number(drawerNetMovement).toFixed(2) + ' ' + drawerSupply.unit"></span>
                                        </div>
                                    </div>
                                    <template x-if="drawerBaseline">
                                        <p class="text-[11px] text-cocoa-400 border-t border-cocoa-100/60 pt-2 leading-relaxed">
                                            <span x-text="drawerBaseline.source_label"></span> established on <span x-text="drawerBaseline.established_at"></span>.
                                            Opening balance + net movements = <strong class="text-cocoa-600" x-text="(Number(drawerBaseline.opening_quantity) + Number(drawerNetMovement)).toFixed(2) + ' ' + drawerSupply.unit"></strong>.
                                        </p>
                                    </template>
                                    <template x-if="!drawerBaseline && !detailLoading">
                                        <p class="text-[11px] text-cocoa-400 border-t border-cocoa-100/60 pt-2">
                                            No opening balance recorded yet. The next stock operation will establish a reconciliation baseline.
                                        </p>
                                    </template>
                                </div>
                            </div>

                            <!-- Recent Movement Activity (Mini Feed) -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-semibold text-cocoa-400 uppercase tracking-wider">Recent Activity</span>
                                    <a :href="drawerSupply.history_url" class="text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 transition">
                                        Full history →
                                    </a>
                                </div>

                                <!-- Loading state -->
                                <div x-show="detailLoading" class="py-3 text-center text-xs text-cocoa-400">
                                    Loading activity...
                                </div>

                                <!-- Empty state -->
                                <div x-show="!detailLoading && drawerMovements.length === 0" class="bg-white border border-cocoa-100 rounded-xl p-4 text-center text-xs text-cocoa-400">
                                    No stock movements recorded for this item yet.
                                </div>

                                <!-- List of recent movements -->
                                <div x-show="!detailLoading && drawerMovements.length > 0" class="space-y-2">
                                    <template x-for="movement in drawerMovements" :key="movement.id">
                                        <div class="bg-white border border-cocoa-100 rounded-lg p-2.5 text-xs flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-bold text-cocoa-700 capitalize" x-text="movement.operation_type"></span>
                                                    <span class="text-cocoa-400">·</span>
                                                    <span class="text-cocoa-400" x-text="movement.date"></span>
                                                </div>
                                                <div class="text-[11px] text-cocoa-400 mt-0.5 truncate" x-text="'By ' + movement.user_name + (movement.notes ? ' — ' + movement.notes : '')"></div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="font-bold block"
                                                      :class="movement.transaction_type === 'stock_out' ? 'text-amber-700' : 'text-emerald-700'"
                                                      x-text="movement.formatted_quantity + ' ' + drawerSupply.unit"></span>
                                                <span class="text-[10px] text-cocoa-400 block" x-text="movement.before_quantity + ' → ' + movement.after_quantity"></span>
                                            </div>
                                        </div>
                                    </template>

                                </div>
                            </div>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="p-3.5 border-t border-cocoa-100 bg-cream-50/60 flex items-center justify-end gap-3">
                            <button type="button" @click="closeDetailDrawer" class="ui-button quiet text-xs py-2 px-4">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
