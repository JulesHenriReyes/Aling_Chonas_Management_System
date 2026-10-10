<fieldset class="customer-paid-extras">
    <legend>Paid extras <span>(optional)</span></legend>
    <p class="form-hint">Choose a little extra for your celebration. Quantities apply to this package line.</p>
    <div class="paid-extras-grid">
        <template x-for="extra in (product(item)?.add_ons || [])" :key="extra.id">
            <div class="extra-card" :class="{ 'is-selected': !!selectedExtra(item, extra.id) }">
                <label class="extra-card-select">
                    <input type="checkbox" :checked="!!selectedExtra(item, extra.id)" @change="toggleExtra(item, extra, $event.target.checked)" :aria-label="'Add ' + extra.name">
                    <span class="extra-card-photo">
                        <template x-if="extra.photo_path"><img :src="'/storage/'+extra.photo_path" alt="" width="240" height="240" loading="lazy"></template>
                        <template x-if="!extra.photo_path"><span class="extra-photo-placeholder"><x-icon name="cake" /><span>Photo coming soon</span></span></template>
                    </span>
                    <span class="extra-card-copy">
                        <strong x-text="extra.name"></strong>
                        <span x-text="money(extra.price)"></span>
                        <span class="extra-card-state" x-text="selectedExtra(item, extra.id) ? 'Selected' : 'Add to package'"></span>
                    </span>
                </label>
                <template x-if="selectedExtra(item, extra.id)">
                    <div class="extra-card-quantity">
                        <input type="hidden" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][add_on_id]'" :value="extra.id">
                        <label :for="'extra-'+item.uid+'-'+extra.id">Quantity</label>
                        <div class="extra-stepper">
                            <button type="button" class="extra-stepper-btn" @click="decreaseExtra(item, extra)" :aria-label="'Decrease ' + extra.name + ' quantity'">−</button>
                            <input :id="'extra-'+item.uid+'-'+extra.id" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][quantity]'" type="number" min="1" max="999" step="1" x-model.number="selectedExtra(item, extra.id).quantity" @change="setExtraQuantity(item, extra, $event.target.value)" @blur="setExtraQuantity(item, extra, $event.target.value)" required class="extra-stepper-input" :aria-label="extra.name + ' quantity'">
                            <button type="button" class="extra-stepper-btn" @click="increaseExtra(item, extra)" :aria-label="'Increase ' + extra.name + ' quantity'">+</button>
                        </div>
                        <p class="extra-line-price" x-text="'Extra total: ' + money(Number(selectedExtra(item, extra.id).quantity || 0) * extra.price)"></p>
                    </div>
                </template>
            </div>
        </template>
    </div>
    <p x-show="!(product(item)?.add_ons.length)" class="form-hint">No paid extras for this package.</p>
</fieldset>
