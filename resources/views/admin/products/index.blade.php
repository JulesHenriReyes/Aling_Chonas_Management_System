@extends('layouts.admin')

@section('title', 'Product Catalog')

@section('content')
<div class="space-y-6" x-data="{
    editingProduct: null,
    showCreateModal: false,
    openEdit(p) {
        this.editingProduct = Object.assign({}, p);
    },
    closeEdit() {
        this.editingProduct = null;
    }
}">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Product Management</h1>
            <p class="text-xs text-stone-500 mt-1">Manage standard bakery offerings and base starting prices.</p>
        </div>
        <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow">
            + Add New Product
        </button>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px]">
                    <tr>
                        <th class="p-4">Product Name</th>
                        <th class="p-4">Catalog Base Price</th>
                        <th class="p-4">Order History</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($products as $p)
                        <tr class="hover:bg-stone-50 transition">
                            <td class="p-4 font-bold text-stone-900">
                                {{ $p->product_name }}
                            </td>
                            <td class="p-4 font-semibold text-rose-950">
                                ₱{{ number_format($p->price, 2) }}
                            </td>
                            <td class="p-4 text-stone-500">
                                {{ $p->order_details_count }} orders
                            </td>
                            <td class="p-4">
                                @if ($p->is_active)
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        Active (Public)
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-500">
                                        Hidden (Inactive)
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button type="button" @click="openEdit({{ $p }})" class="text-xs text-rose-900 font-bold hover:underline">
                                    Edit
                                </button>
                                <form action="{{ route('products.toggleStatus', $p) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs {{ $p->is_active ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }} hover:underline">
                                        {{ $p->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200" @click.away="showCreateModal = false">
            <h3 class="text-lg font-bold text-stone-900 mb-4">Add New Bakery Product</h3>
            <form action="{{ route('products.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="product_name" required placeholder="e.g. Red Velvet Heart Cake" class="w-full text-xs rounded-xl border-stone-300">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Catalog Base Price (₱) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="price" required placeholder="e.g. 850.00" class="w-full text-xs rounded-xl border-stone-300">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="create_active" checked class="rounded text-rose-900">
                    <label for="create_active" class="text-xs text-stone-700 font-semibold">Active & Visible in Public Ordering</label>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs text-stone-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-900 text-white font-bold text-xs rounded-xl shadow">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editingProduct" x-cloak class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200" @click.away="closeEdit()">
            <h3 class="text-lg font-bold text-stone-900 mb-4">Edit Product Details</h3>
            <form :action="'/products/' + editingProduct.id" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="product_name" x-model="editingProduct.product_name" required class="w-full text-xs rounded-xl border-stone-300">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Catalog Base Price (₱) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="price" x-model="editingProduct.price" required class="w-full text-xs rounded-xl border-stone-300">
                    <p class="text-[10px] text-stone-400 mt-1">Note: Modifying this price will only affect new orders; existing confirmed orders remain locked.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="edit_active" :checked="editingProduct.is_active" class="rounded text-rose-900">
                    <label for="edit_active" class="text-xs text-stone-700 font-semibold">Active & Visible in Public Ordering</label>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <button type="button" @click="closeEdit()" class="px-4 py-2 text-xs text-stone-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-900 text-white font-bold text-xs rounded-xl shadow">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
