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
            <h1 class="text-2xl font-bold text-stone-900">Inventory & Supplies</h1>
            <p class="text-xs text-stone-500 mt-1">Track bakery ingredients, packaging materials, stock levels, and usage.</p>
        </div>
        <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow self-start sm:self-auto">
            + Add New Supply
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2">
            <a href="{{ route('supplies.index') }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ !request()->hasAny(['category', 'low_stock']) ? 'bg-rose-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}">
                All Supplies
            </a>
            <a href="{{ route('supplies.index', ['category' => 'ingredients']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ request('category') === 'ingredients' ? 'bg-rose-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}">
                Ingredients
            </a>
            <a href="{{ route('supplies.index', ['category' => 'packaging']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ request('category') === 'packaging' ? 'bg-rose-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}">
                Packaging
            </a>
            <a href="{{ route('supplies.index', ['low_stock' => '1']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ request('low_stock') ? 'bg-red-700 text-white' : 'text-red-700 hover:bg-red-50' }}">
                ⚠️ Low Stock Alert Only
            </a>
        </div>
    </div>

    <!-- Supplies Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px]">
                    <tr>
                        <th class="p-4">Supply Item</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Current Stock</th>
                        <th class="p-4">Reorder Level</th>
                        <th class="p-4">Stock Status</th>
                        <th class="p-4 text-right">Quick Stock Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($supplies as $s)
                        <tr class="hover:bg-stone-50 transition">
                            <td class="p-4 font-bold text-stone-900">
                                {{ $s->supply_name }}
                            </td>
                            <td class="p-4 uppercase text-[10px] font-semibold text-stone-500">
                                {{ $s->category }}
                            </td>
                            <td class="p-4 font-mono font-bold text-stone-900 text-sm">
                                {{ number_format($s->current_quantity, 2) }} <span class="text-xs font-normal text-stone-500">{{ $s->unit }}</span>
                            </td>
                            <td class="p-4 font-mono text-stone-500">
                                {{ number_format($s->reorder_level, 2) }} {{ $s->unit }}
                            </td>
                            <td class="p-4">
                                @if ($s->current_quantity <= $s->reorder_level)
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 animate-pulse">
                                        ⚠️ Low Stock
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        ✓ In Stock
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-1.5">
                                <button type="button" @click="openStockModal({{ $s }}, 'stock_in')" 
                                        class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold rounded-lg text-xs">
                                    + Stock In
                                </button>
                                <button type="button" @click="openStockModal({{ $s }}, 'stock_out')" 
                                        class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-800 font-bold rounded-lg text-xs">
                                    - Stock Out
                                </button>
                                <button type="button" @click="openStockModal({{ $s }}, 'adjustment')" 
                                        class="px-2.5 py-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-lg text-xs">
                                    Adjust
                                </button>
                                <button type="button" @click="openEditSupply({{ $s }})" 
                                        class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-900 font-bold rounded-lg text-xs">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-stone-400">No supplies recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stock Transaction Modal -->
    <div x-show="selectedSupply" x-cloak class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200" @click.away="closeStockModal()">
            <h3 class="text-base font-bold text-stone-900 mb-1" x-text="'Record ' + (stockAction === 'stock_in' ? 'Stock In' : (stockAction === 'stock_out' ? 'Stock Out (Usage)' : 'Inventory Adjustment'))"></h3>
            <p class="text-xs text-stone-500 mb-4" x-text="'Item: ' + (selectedSupply ? selectedSupply.supply_name : '') + ' (Current: ' + (selectedSupply ? selectedSupply.current_quantity : '') + ' ' + (selectedSupply ? selectedSupply.unit : '') + ')'"></p>

            <form :action="'/supplies/' + (selectedSupply ? selectedSupply.id : '') + '/transactions'" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="transaction_type" :value="stockAction">

                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">
                        Quantity (<span x-text="selectedSupply ? selectedSupply.unit : ''"></span>) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" step="0.01" name="quantity" x-model="stockQuantity" required 
                           :placeholder="stockAction === 'adjustment' ? 'e.g. +2 or -2' : 'Positive quantity'" 
                           class="w-full text-xs rounded-xl border-stone-300">
                    <p class="text-[10px] text-stone-400 mt-1" x-show="stockAction === 'stock_out'">
                        Stock out cannot exceed current stock level. Negative stock is strictly prevented.
                    </p>
                    <p class="text-[10px] text-stone-400 mt-1" x-show="stockAction === 'adjustment'">
                        Enter positive number to increase, or negative number (e.g. -1.5) to decrease.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Transaction Notes / Reason</label>
                    <input type="text" name="notes" x-model="stockNotes" placeholder="e.g. Delivery from Supplier, Batch production baking, Spoilage" 
                           class="w-full text-xs rounded-xl border-stone-300">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t">
                    <button type="button" @click="closeStockModal()" class="px-4 py-2 text-xs text-stone-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-900 text-white font-bold text-xs rounded-xl shadow">Confirm Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Supply Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200" @click.away="showCreateModal = false">
            <h3 class="text-lg font-bold text-stone-900 mb-4">Add Master Supply Item</h3>
            <form action="{{ route('supplies.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Supply Name <span class="text-red-500">*</span></label>
                    <input type="text" name="supply_name" required placeholder="e.g. Powdered Sugar" class="w-full text-xs rounded-xl border-stone-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category" required class="w-full text-xs rounded-xl border-stone-300">
                            <option value="ingredients">Ingredients</option>
                            <option value="packaging">Packaging</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Unit of Measure <span class="text-red-500">*</span></label>
                        <input type="text" name="unit" required placeholder="e.g. kg, pcs, box" class="w-full text-xs rounded-xl border-stone-300">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Initial Quantity <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="current_quantity" value="0" required class="w-full text-xs rounded-xl border-stone-300">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Reorder Level <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="reorder_level" value="5" required class="w-full text-xs rounded-xl border-stone-300">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs text-stone-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-900 text-white font-bold text-xs rounded-xl shadow">Save Supply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Supply Modal -->
    <div x-show="editingSupply" x-cloak class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200" @click.away="closeEditSupply()">
            <h3 class="text-lg font-bold text-stone-900 mb-4">Edit Supply Item</h3>
            <form :action="'/supplies/' + (editingSupply ? editingSupply.id : '')" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Supply Name <span class="text-red-500">*</span></label>
                    <input type="text" name="supply_name" x-model="editingSupply.supply_name" required class="w-full text-xs rounded-xl border-stone-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category" x-model="editingSupply.category" required class="w-full text-xs rounded-xl border-stone-300">
                            <option value="ingredients">Ingredients</option>
                            <option value="packaging">Packaging</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Unit of Measure <span class="text-red-500">*</span></label>
                        <input type="text" name="unit" x-model="editingSupply.unit" required class="w-full text-xs rounded-xl border-stone-300">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Reorder Level <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="reorder_level" x-model="editingSupply.reorder_level" required class="w-full text-xs rounded-xl border-stone-300">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="edit_supply_active" :checked="editingSupply && editingSupply.is_active" class="rounded text-rose-900">
                    <label for="edit_supply_active" class="text-xs text-stone-700 font-semibold">Active Supply</label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <button type="button" @click="closeEditSupply()" class="px-4 py-2 text-xs text-stone-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-900 text-white font-bold text-xs rounded-xl shadow">Update Supply</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
