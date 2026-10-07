<!-- Edit Customer Popup Modal -->
<div x-show="editModalOpen"
     x-cloak
     data-dialog
     role="dialog"
     aria-modal="true"
     aria-labelledby="edit-customer-modal-title"
     tabindex="-1"
     @keydown.escape.window="editModalOpen = false"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
    <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-lg w-full p-6 shadow-xl"
         x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
         @click.away="editModalOpen = false">
        <div class="flex items-center justify-between pb-3 border-b border-cocoa-100/60 mb-4">
            <div>
                <h2 id="edit-customer-modal-title" class="text-base font-bold text-cocoa-600">Edit Customer Details</h2>
                <p class="text-xs text-cocoa-400 mt-0.5">Update personal name and verified contact number.</p>
            </div>
            <button type="button"
                    data-dialog-close
                    @click="editModalOpen = false"
                    aria-label="Close dialog"
                    class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition">
                <x-icon name="close" class="w-5 h-5" />
            </button>
        </div>

        <form :action="editCustomer.update_url" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit_customer">
            @if(isset($fromIndex) && $fromIndex)
                <input type="hidden" name="_from" value="index">
            @endif
            <input type="hidden" name="customer_id" :value="editCustomer.id">

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="modal-customer-first-name">First Name <span class="text-red-500">*</span></label>
                <input autocomplete="given-name" id="modal-customer-first-name" type="text" name="first_name" x-model="editCustomer.first_name" required 
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="modal-customer-middle-name">Middle Name <span class="text-cocoa-400 font-normal normal-case">(Optional)</span></label>
                <input autocomplete="additional-name" id="modal-customer-middle-name" type="text" name="middle_name" x-model="editCustomer.middle_name" 
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                @error('middle_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="modal-customer-last-name">Last Name <span class="text-red-500">*</span></label>
                <input autocomplete="family-name" id="modal-customer-last-name" type="text" name="last_name" x-model="editCustomer.last_name" required 
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="modal-customer-phone-number">Phone Number <span class="text-red-500">*</span></label>
                <input autocomplete="tel" id="modal-customer-phone-number" type="tel" name="phone_number" maxlength="40" x-model="editCustomer.phone_number" required
                       class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                <p class="text-xs text-cocoa-400 mt-1">For landlines, include the area code (e.g. 02 8123 4567). Mobile +63 numbers accepted.</p>
                @error('phone_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-cocoa-100/60 mt-4">
                <button type="button" data-dialog-close @click="editModalOpen = false" class="ui-button quiet">Cancel</button>
                <button type="submit" class="ui-button primary">Update Details</button>
            </div>
        </form>
    </div>
</div>
