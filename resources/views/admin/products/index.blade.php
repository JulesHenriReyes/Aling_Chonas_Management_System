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
    <div class="page-heading">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Product Management</h1>
            <p class="text-sm text-cocoa-400 mt-1">Manage standard bakery offerings and base starting prices.</p>
        </div>
        <button type="button" @click="showCreateModal = true" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition"><x-icon name="plus" class="mr-1" /> Add New Product
        </button>
    </div>

    <!-- Products Table -->
    <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
        <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
            <table class="w-full text-left">
                <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                    <tr>
                        <th class="p-4">Product Name</th>
                        <th class="p-4">Catalog Base Price</th>
                        <th class="p-4">Order History</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cocoa-100/60">
                    @foreach ($products as $p)
                        <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                            <td class="p-4 font-semibold text-cocoa-600">
                                {{ $p->product_name }}
                            </td>
                            <td class="p-4 font-semibold">
                                ₱{{ number_format($p->price, 2) }}
                            </td>
                            <td class="p-4">
                                {{ $p->order_details_count }} orders
                            </td>
                            <td class="p-4">
                                @if ($p->is_active)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cream-100 text-cocoa-500 ">
                                        Active (Public)
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cocoa-50 text-cocoa-500 ">
                                        Hidden (Inactive)
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-3">
                                <button type="button" @click="openEdit({{ $p }})" class="text-cocoa-500 hover:text-cocoa-600 font-medium">
                                    Edit
                                </button>
                                <form action="{{ route('products.toggleStatus', $p) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="font-medium {{ $p->is_active ? 'text-cocoa-500 hover:text-cocoa-700' : 'text-cocoa-500 hover:text-cocoa-700' }}">
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
    <div x-show="showCreateModal" x-cloak data-dialog role="dialog" aria-modal="true" aria-labelledby="product-dialog-1" tabindex="-1"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-md w-full p-6 shadow-xl"
             x-show="showCreateModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
             @click.away="showCreateModal = false">
            <h3 id="product-dialog-1" class="text-sm font-semibold text-cocoa-600 mb-4">Add New Bakery Product</h3>
            <form action="{{ route('products.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-products-index-blade-php-1">product name <span class="text-red-500">*</span></label>
                    <input id="field-admin-products-index-blade-php-1" type="text" name="product_name" required placeholder="e.g. Red Velvet Heart Cake" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-products-index-blade-php-2">catalog base price (₱) <span class="text-red-500">*</span></label>
                    <input id="field-admin-products-index-blade-php-2" type="number" step="0.01" min="0" name="price" required placeholder="e.g. 850.00" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="create_active" checked class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                    <label for="create_active" class="text-sm font-medium text-cocoa-500">active & Visible in Public Ordering</label>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button" data-dialog-close @click="showCreateModal = false" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</button>
                    <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editingProduct" x-cloak data-dialog role="dialog" aria-modal="true" aria-labelledby="product-dialog-2" tabindex="-1"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-md w-full p-6 shadow-xl"
             x-show="editingProduct"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
             @click.away="closeEdit()">
            <h3 id="product-dialog-2" class="text-sm font-semibold text-cocoa-600 mb-4">Edit Product Details</h3>
            <template x-if="editingProduct"><form :action="'/products/' + editingProduct.id" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5">product name <span class="text-red-500">*</span></label>
                    <input type="text" name="product_name" x-model="editingProduct.product_name" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-products-index-blade-php-3">catalog base price (₱) <span class="text-red-500">*</span></label>
                    <input id="field-admin-products-index-blade-php-3" type="number" step="0.01" min="0" name="price" x-model="editingProduct.price" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                    <p class="text-xs text-cocoa-400 mt-1">Note: Modifying this price will only affect new orders; existing confirmed orders remain locked.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="edit_active" :checked="editingProduct.is_active" class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                    <label for="edit_active" class="text-sm font-medium text-cocoa-500">active & Visible in Public Ordering</label>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button" data-dialog-close @click="closeEdit()" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</button>
                    <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">Update Product</button>
                </div>
            </form></template>
        </div>
    </div>
</div>
@endsection
