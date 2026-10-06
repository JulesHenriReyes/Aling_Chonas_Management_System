@extends('layouts.admin')
@section('title', 'Inventory & supplies')
@section('content')
<div class="workspace" x-data="{
    showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
    showDetailDrawer: false,
    detailLoading: false,
    headerHeight: 56,
    drawerSupply: null,
    drawerBaseline: null,
    drawerMovements: [],
    drawerNetMovement: 0,
    updateHeaderHeight() {
        const topbar = document.getElementById('admin-topbar') || document.querySelector('header.sticky') || document.querySelector('header');
        if (topbar) {
            const rect = topbar.getBoundingClientRect();
            const bottom = Math.max(rect.bottom, topbar.offsetHeight || 56);
            this.headerHeight = Math.max(48, Math.ceil(bottom));
        }
    },
    init() {
        this.updateHeaderHeight();
        const topbar = document.getElementById('admin-topbar') || document.querySelector('header.sticky');
        if (topbar && window.ResizeObserver) {
            const ro = new ResizeObserver(() => this.updateHeaderHeight());
            ro.observe(topbar);
        }
        window.addEventListener('resize', () => this.updateHeaderHeight());
        window.addEventListener('scroll', () => this.updateHeaderHeight(), { passive: true });
    },
    openSupplyDetail(supply) {
        this.updateHeaderHeight();
        this.drawerSupply = supply;
        this.showDetailDrawer = true;
        this.detailLoading = true;
        this.drawerBaseline = null;
        this.drawerMovements = [];
        this.drawerNetMovement = 0;

        fetch('/supplies/' + supply.id, {
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
            if (this.drawerSupply && this.drawerSupply.id === supply.id) {
                this.drawerSupply = { ...this.drawerSupply, ...data.supply, ...data.routes };
                this.drawerBaseline = data.baseline;
                this.drawerMovements = data.movements || [];
                this.drawerNetMovement = data.net_movement || 0;
                this.detailLoading = false;
            }
        })
        .catch(() => {
            this.detailLoading = false;
        });
    },
    closeDetailDrawer() {
        this.showDetailDrawer = false;
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
        <input type="hidden" name="sort" value="{{ request('sort','supply_name') }}">
        <input type="hidden" name="direction" value="{{ request('direction','asc') }}">
        <button class="ui-button">Apply</button>
        <a class="ui-button quiet" href="{{ route('supplies.index') }}">Reset</a>
    </form>

    <div class="workspace-table table-scroll" role="region" aria-label="Supplies table" tabindex="0">
        <table class="supplies-table">
            <colgroup>
                <col>
                <col class="supplies-category-col">
                <col class="supplies-stock-col">
                <col class="supplies-status-col">
                <col class="supplies-actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th><x-sort-heading column="supply_name" label="Supply" /></th>
                    <th><x-sort-heading column="category" label="Category" /></th>
                    <th>On hand</th>
                    <th><span class="inline-flex items-center gap-1">Stock status <x-tooltip text="Inventory levels automatically categorized as In stock, Low stock, or Out of stock based on reorder thresholds." /></span></th>
                    <th scope="col" aria-label="Supply options"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($supplies as $supply)
                    @php
                        $supplyData = [
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
                            'is_out_of_stock' => (float) $supply->current_quantity == 0,
                            'edit_url' => route('supplies.edit', $supply),
                            'history_url' => route('inventory.history', ['supply_id' => $supply->id]),
                            'stock_in_url' => route('inventory.create', ['type' => 'receipt', 'supply_id' => $supply->id]),
                            'stock_out_url' => route('inventory.create', ['type' => 'usage', 'supply_id' => $supply->id]),
                        ];
                    @endphp
                    <tr class="cursor-pointer hover:bg-cream-100/60 transition group"
                        @click="openSupplyDetail(@js($supplyData))">
                        <td>
                            <a class="record-link font-semibold group-hover:text-cocoa-700"
                               href="{{ route('supplies.show', $supply) }}"
                               @click.prevent.stop="openSupplyDetail(@js($supplyData))">
                                {{ $supply->supply_name }}
                            </a>
                            @unless($supply->is_active)<span class="status">Inactive</span>@endunless
                        </td>
                        <td>{{ ucfirst($supply->category) }}</td>
                        <td class="font-medium text-cocoa-600">{{ number_format($supply->current_quantity, 2) }} {{ $supply->unit }}</td>
                        <td>
                            @if($supply->current_quantity == 0)
                                <x-status value="out_of_stock" label="Out of stock" />
                            @elseif($supply->is_low_stock)
                                <x-status value="low_stock" label="Low stock" />
                            @else
                                <x-status value="in_stock" label="In stock" />
                            @endif
                        </td>
                        <td @click.stop>
                            <details class="row-actions supply-actions">
                                <summary aria-label="Options for {{ $supply->supply_name }}"><x-icon name="dots-vertical" /></summary>
                                <div>
                                    <a href="{{ route('supplies.edit', $supply) }}">Edit</a>
                                    <a href="{{ route('supplies.show', $supply) }}"
                                       role="button"
                                       @click.prevent="openSupplyDetail(@js($supplyData)); $el.closest('details').removeAttribute('open')">
                                        Details
                                    </a>
                                    <a href="{{ route('inventory.history', ['supply_id' => $supply->id]) }}">History</a>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">No supplies match these filters. <a href="{{ route('supplies.index') }}">Clear filters</a> or add a supply.</td>
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

                    <div>
                        <label for="modal-unit" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Stock unit <span class="text-red-500">*</span></label>
                        <input id="modal-unit"
                               name="unit"
                               type="text"
                               required
                               maxlength="50"
                               placeholder="e.g. kg, piece, can, ml"
                               value="{{ old('unit', 'kg') }}"
                               list="modal-stock-units"
                               class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                        <datalist id="modal-stock-units">
                            @foreach(config('inventory.stock_units', ['kg', 'g', 'piece', 'can', 'ml', 'litre']) as $unit)
                                <option value="{{ $unit }}">
                            @endforeach
                        </datalist>
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
                    <p class="text-[11px] text-cocoa-400 mt-1">Triggers "Low stock" warning when current quantity falls to or below this level.</p>
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

    <!-- Sliding Detail Drawer from Right (Anchored strictly below the header in all zoom/size states) -->
    <div x-show="showDetailDrawer"
         x-cloak
         data-detail-drawer
         @keydown.escape.window="closeDetailDrawer"
         class="fixed left-0 right-0 bottom-0 z-30 overflow-hidden"
         :style="'top: ' + headerHeight + 'px; height: calc(100dvh - ' + headerHeight + 'px);'"
         style="display: none;">

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
                 class="w-screen max-w-md sm:max-w-[420px] bg-white border-l border-t border-cocoa-100 shadow-2xl flex flex-col h-full pointer-events-auto"
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
                                <h2 class="text-base font-bold text-cocoa-700 truncate" x-text="drawerSupply.supply_name"></h2>
                            </div>
                            <button type="button"
                                    @click="closeDetailDrawer"
                                    class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition focus:outline-none shrink-0"
                                    aria-label="Close detail panel">
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
                                        <strong>Stock depleted.</strong> Immediate stock in is recommended to avoid production delays.
                                    </div>
                                </template>
                                <template x-if="!drawerSupply.is_out_of_stock && drawerSupply.is_low_stock">
                                    <div class="text-xs bg-amber-50 text-amber-800 p-2.5 rounded-lg border border-amber-200/80">
                                        <strong>Low stock alert:</strong> Quantity is at or below the reorder threshold (<span x-text="drawerSupply.formatted_reorder_level + ' ' + drawerSupply.unit"></span>).
                                    </div>
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
                                    <a :href="drawerSupply.edit_url" class="ui-button quiet text-xs justify-center py-2">
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

                                    <a :href="drawerSupply.history_url" class="block w-full text-center text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 bg-cream-50 hover:bg-cream-100 border border-cocoa-100/80 rounded-lg py-2 transition mt-2">
                                        View complete movement history →
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="p-3.5 border-t border-cocoa-100 bg-cream-50/60 flex items-center justify-between gap-3">
                            <a :href="drawerSupply.history_url" class="text-xs font-semibold text-cocoa-600 hover:underline">
                                Movement history
                            </a>
                            <button type="button" @click="closeDetailDrawer" class="ui-button quiet text-xs">
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
