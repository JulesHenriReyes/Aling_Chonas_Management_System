@extends('layouts.admin')

@section('title', 'Supply & Inventory Management')

@section('content')
<div class="space-y-6" x-data="{
    showCreateModal: false,
    selectedSupply: null,
    stockAction: 'stock_in',
    stockQuantity: '',
    stockNotes: '',
    editingSupply: null,
    openEditSupply(supply) {
        this.editingSupply = Object.assign({}, supply);
    },
    closeEditSupply() {
        this.editingSupply = null;
    },
    openStockModal(supply, action) {
        this.selectedSupply = supply;
        this.stockAction = action;
        this.stockQuantity = '';
        this.stockNotes = '';
    },
    closeStockModal() {
        this.selectedSupply = null;
    }
}">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Inventory & Supplies</h1>
            <p class="text-sm text-cocoa-400 mt-1">Track bakery ingredients, packaging materials, stock levels, and usage.</p>
        </div>
        <button type="button" @click="showCreateModal = true" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto"><x-icon name="plus" class="mr-1" /> Add New Supply
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('supplies.index') }}" 
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ !request()->hasAny(['category', 'low_stock']) ? 'bg-cocoa-600 text-white' : 'text-cocoa-500 hover:bg-cream-100' }}">
                All Supplies
            </a>
            <a href="{{ route('supplies.index', ['category' => 'ingredients']) }}" 
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ request('category') === 'ingredients' ? 'bg-cocoa-600 text-white' : 'text-cocoa-500 hover:bg-cream-100' }}">
                Ingredients
            </a>
            <a href="{{ route('supplies.index', ['category' => 'packaging']) }}" 
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ request('category') === 'packaging' ? 'bg-cocoa-600 text-white' : 'text-cocoa-500 hover:bg-cream-100' }}">
                Packaging
            </a>
            <a href="{{ route('supplies.index', ['low_stock' => '1']) }}" 
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ request('low_stock') ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50' }}">
                <x-icon name="warning" /> Low stock
            </a>
        </div>
    </div>

    <!-- Supplies Table -->
    <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
        <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
            <table class="w-full text-left inventory-table">
                <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                    <tr>
                        <th class="p-4">Supply Item</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Current Stock</th>
                        <th class="p-4">Reorder Level</th>
                        <th class="p-4">Stock Status</th>
                        <th class="p-4 text-right">Quick Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cocoa-100/60">
                    @forelse ($supplies as $s)
                        <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                            <td class="p-4 font-semibold text-cocoa-600">
                                {{ $s->supply_name }}
                            </td>
                            <td class="p-4 text-xs font-semibold">
                                {{ $s->category }}
                            </td>
                            <td class="p-4 font-semibold text-cocoa-600">
                                {{ number_format($s->current_quantity, 2) }} <span class="font-normal text-cocoa-400">{{ $s->unit }}</span>
                            </td>
                            <td class="p-4 text-cocoa-400">
                                {{ number_format($s->reorder_level, 2) }} {{ $s->unit }}
                            </td>
                            <td class="p-4">
                                @if ($s->current_quantity <= $s->reorder_level)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 ring-1 ring-red-200">
                                        <x-icon name="warning" /> Low stock
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cream-100 text-cocoa-500 ">
                                        In Stock
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button type="button" @click="openStockModal({{ $s }}, 'stock_in')" 
                                        class="px-3 py-1.5 bg-cream-100 hover:bg-cocoa-100 text-cocoa-600 font-medium rounded-lg text-xs transition">
                                    Stock In
                                </button>
                                <button type="button" @click="openStockModal({{ $s }}, 'stock_out')" 
                                        class="px-3 py-1.5 bg-cream-100 hover:bg-cocoa-100 text-cocoa-600 font-medium rounded-lg text-xs transition">
                                    Stock Out
                                </button>
                                <button type="button" @click="openStockModal({{ $s }}, 'adjustment')" 
                                        class="px-3 py-1.5 bg-cocoa-50 hover:bg-cocoa-100 text-cocoa-600 font-medium rounded-lg text-xs transition">
                                    Adjust
                                </button>
                                <button type="button" @click="openEditSupply({{ $s }})" 
                                        class="text-cocoa-500 hover:text-cocoa-600 font-medium px-2">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-sm text-cocoa-400">No supplies recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stock Transaction Modal -->
    <div x-show="selectedSupply" x-cloak data-dialog role="dialog" aria-modal="true" aria-labelledby="supply-dialog-1" tabindex="-1" class="dialog-overlay fixed inset-0 bg-cocoa-800/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-md w-full p-6 " @click.away="closeStockModal()">
            <h3 id="supply-dialog-1" class="text-sm font-semibold text-cocoa-600 mb-1" x-text="'Record ' + (stockAction === 'stock_in' ? 'Stock In' : (stockAction === 'stock_out' ? 'Stock Out (Usage)' : 'Inventory Adjustment'))"></h3>
            <p class="text-xs text-cocoa-400 mb-4" x-text="'Item: ' + (selectedSupply ? selectedSupply.supply_name : '') + ' (Current: ' + (selectedSupply ? selectedSupply.current_quantity : '') + ' ' + (selectedSupply ? selectedSupply.unit : '') + ')'"></p>

            <form :action="'/supplies/' + (selectedSupply ? selectedSupply.id : '') + '/transactions'" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="transaction_type" :value="stockAction">

                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-1">
                        Quantity (<span x-text="selectedSupply ? selectedSupply.unit : ''"></span>) <span class="text-red-500">*</span>
                    </label>
                    <input id="field-admin-supplies-index-blade-php-1" type="number" step="0.01" name="quantity" x-model="stockQuantity" required 
                           :placeholder="stockAction === 'adjustment' ? 'e.g. +2 or -2' : 'Positive quantity'" 
                           class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                    <p class="text-xs text-cocoa-400 mt-1" x-show="stockAction === 'stock_out'">
                        Stock out cannot exceed current stock level.
                    </p>
                    <p class="text-xs text-cocoa-400 mt-1" x-show="stockAction === 'adjustment'">
                        Enter positive number to increase, or negative to decrease.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-2">Transaction notes</label>
                    <input id="field-admin-supplies-index-blade-php-2" type="text" name="notes" x-model="stockNotes" placeholder="e.g. Delivery from Supplier, Baking batch" 
                           class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button" data-dialog-close @click="closeStockModal()" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</button>
                    <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">Confirm Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Supply Modal -->
    <div x-show="showCreateModal" x-cloak data-dialog role="dialog" aria-modal="true" aria-labelledby="supply-dialog-2" tabindex="-1" class="dialog-overlay fixed inset-0 bg-cocoa-800/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-md w-full p-6 " @click.away="showCreateModal = false">
            <h3 id="supply-dialog-2" class="text-sm font-semibold text-cocoa-600 mb-4">Add Supply Item</h3>
            <form action="{{ route('supplies.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-3">supply name <span class="text-red-500">*</span></label>
                    <input id="field-admin-supplies-index-blade-php-3" type="text" name="supply_name" required placeholder="e.g. Powdered Sugar" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-4">category <span class="text-red-500">*</span></label>
                        <select id="field-admin-supplies-index-blade-php-4" name="category" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                            <option value="ingredients">Ingredients</option>
                            <option value="packaging">Packaging</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-5">unit of measure <span class="text-red-500">*</span></label>
                        <input id="field-admin-supplies-index-blade-php-5" type="text" name="unit" required placeholder="e.g. kg, pcs, box" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-6">initial Qty <span class="text-red-500">*</span></label>
                        <input id="field-admin-supplies-index-blade-php-6" type="number" step="0.01" min="0" name="current_quantity" value="0" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-7">Reorder level <span class="text-red-500">*</span></label>
                        <input id="field-admin-supplies-index-blade-php-7" type="number" step="0.01" min="0" name="reorder_level" value="5" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button" data-dialog-close @click="showCreateModal = false" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</button>
                    <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">Save Supply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Supply Modal -->
    <div x-show="editingSupply" x-cloak data-dialog role="dialog" aria-modal="true" aria-labelledby="supply-dialog-3" tabindex="-1" class="dialog-overlay fixed inset-0 bg-cocoa-800/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-md w-full p-6 " @click.away="closeEditSupply()">
            <h3 id="supply-dialog-3" class="text-sm font-semibold text-cocoa-600 mb-4">Edit Supply Item</h3>
            <template x-if="editingSupply"><form :action="'/supplies/' + (editingSupply ? editingSupply.id : '')" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-8">supply name <span class="text-red-500">*</span></label>
                    <input id="field-admin-supplies-index-blade-php-8" type="text" name="supply_name" x-model="editingSupply.supply_name" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-9">category <span class="text-red-500">*</span></label>
                        <select id="field-admin-supplies-index-blade-php-9" name="category" x-model="editingSupply.category" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                            <option value="ingredients">Ingredients</option>
                            <option value="packaging">Packaging</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-10">unit <span class="text-red-500">*</span></label>
                        <input id="field-admin-supplies-index-blade-php-10" type="text" name="unit" x-model="editingSupply.unit" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-supplies-index-blade-php-11">Reorder level <span class="text-red-500">*</span></label>
                    <input id="field-admin-supplies-index-blade-php-11" type="number" step="0.01" min="0" name="reorder_level" x-model="editingSupply.reorder_level" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="edit_supply_active" :checked="editingSupply && editingSupply.is_active" class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                    <label for="edit_supply_active" class="text-sm font-medium text-cocoa-500">active supply</label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button" data-dialog-close @click="closeEditSupply()" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</button>
                    <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">Update Supply</button>
                </div>
            </form></template>
        </div>
    </div>
</div>
@endsection
