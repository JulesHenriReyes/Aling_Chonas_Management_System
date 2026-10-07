<!-- Edit Supply Popup Modal -->
<div x-show="showEditModal"
     x-cloak
     data-dialog
     role="dialog"
     aria-modal="true"
     aria-labelledby="edit-supply-modal-title"
     tabindex="-1"
     @keydown.escape.window="showEditModal = false"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
    <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-lg w-full p-6 shadow-xl"
         x-show="showEditModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
         @click.away="showEditModal = false">
        <div class="flex items-center justify-between pb-3 border-b border-cocoa-100/60 mb-4">
            <div>
                <h2 id="edit-supply-modal-title" class="text-base font-bold text-cocoa-600">Edit Supply</h2>
                <p class="text-xs text-cocoa-400 mt-0.5">Update supply name, category, threshold, and active status.</p>
            </div>
            <button type="button"
                    data-dialog-close
                    @click="showEditModal = false"
                    aria-label="Close dialog"
                    class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition">
                <x-icon name="close" class="w-5 h-5" />
            </button>
        </div>

        <form :action="editSupply.update_url" method="POST" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_form" value="edit_supply">
            @if(isset($fromIndex) && $fromIndex)
                <input type="hidden" name="_from" value="index">
            @endif
            <input type="hidden" name="supply_id" :value="editSupply.id">
            <input type="hidden" name="unit" :value="editSupply.unit">

            <div>
                <label for="edit-modal-supply-name" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Supply name <span class="text-red-500">*</span></label>
                <input id="edit-modal-supply-name"
                       name="supply_name"
                       type="text"
                       required
                       maxlength="255"
                       x-model="editSupply.supply_name"
                       placeholder="e.g. All-purpose flour, Cocoa powder, Cake box 8x8"
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                @error('supply_name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit-modal-category" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Category <span class="text-red-500">*</span></label>
                    <select id="edit-modal-category"
                            name="category"
                            required
                            x-model="editSupply.category"
                            class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                        <option value="ingredients">Ingredients</option>
                        <option value="packaging">Packaging</option>
                    </select>
                    @error('category')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5">Stock unit</label>
                    <input type="text"
                           :value="editSupply.unit"
                           disabled
                           class="w-full text-sm rounded-lg border border-cocoa-100 bg-stone-100 px-3 py-2 text-cocoa-500 cursor-not-allowed">
                    <p class="text-[11px] text-cocoa-400 mt-1">Stock unit cannot be changed after creation.</p>
                </div>
            </div>

            <div>
                <label for="edit-modal-reorder-level" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Reorder alert threshold <span class="text-red-500">*</span></label>
                <input id="edit-modal-reorder-level"
                       name="reorder_level"
                       type="number"
                       min="0"
                       step="0.01"
                       max="99999999.99"
                       required
                       x-model="editSupply.reorder_level"
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                <p class="text-[11px] text-cocoa-400 mt-1">Triggers "Low stock" warning when usable quantity falls below this level.</p>
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
                           x-model="editSupply.is_active"
                           class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                    <span>Active supply (available for recipes and stock movements)</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-cocoa-100/60 mt-4">
                <button type="button"
                        data-dialog-close
                        @click="showEditModal = false"
                        class="ui-button quiet">
                    Cancel
                </button>
                <button type="submit"
                        class="ui-button primary">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>
