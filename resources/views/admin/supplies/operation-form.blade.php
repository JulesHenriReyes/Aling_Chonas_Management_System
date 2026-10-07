@extends('layouts.admin')
@section('title', $type === 'receipt' ? 'Stock in' : 'Stock out')
@section('content')
<script src="{{ asset('js/inventory-workspace.js') }}?v={{ filemtime(public_path('js/inventory-workspace.js')) }}"></script>
<div class="workspace inventory-workspace" x-data="stockOperation(@js($type), @js($initialLines), @js(route('supplies.lookup')), @js(route('supplies.store')), @js(csrf_token()), @js(route('inventory.preview')), @js($errors->messages()))">
    <header class="workspace-heading">
        <div>
            <a class="back-link" data-cancel @click="window.__suppressUnload = true" href="{{ route('supplies.index') }}">← Inventory</a>
            <h1>{{ $type === 'receipt' ? 'Stock in' : 'Stock out' }}</h1>
            <p>Each stock-in creates a separate entry. Baking uses the entries expiring first.</p>
        </div>
    </header>

    <form class="workspace-form" x-ref="stockForm" data-safe-form action="{{ route('inventory.store') }}" method="POST" @submit="submit($event)">
        @csrf
        <input type="hidden" name="business_date" :value="businessDate" :disabled="type !== 'usage'">
        <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
        
        <div class="form-grid">
            @if(in_array($type, ['usage', 'waste', 'stocktake']))
                <div>
                    <label for="operation-type">How did stock change?</label>
                    <select id="operation-type" name="type" :value="type" @change="changeType($event.target.value)">
                        <option value="usage">Used for baking</option>
                        <option value="waste">Waste / spoilage</option>
                        <option value="stocktake">Count remaining stock</option>
                    </select>
                    <p class="form-hint" x-show="type === 'stocktake'" x-cloak>A stock count sets the quantity on hand. It can increase or decrease recorded stock.</p>
                </div>
            @else
                <input type="hidden" name="type" :value="type">
            @endif

            <div>
                <label for="operation_date">{{ $type === 'receipt' ? 'Stock-in date' : 'Effective date' }}</label>
                <input type="date" id="operation_date" name="operation_date" required value="{{ old('operation_date', \App\Support\InventoryCalendar::date()) }}">
            </div>

            @if($type === 'receipt')
                <div>
                    <label for="supplier">Supplier (optional)</label>
                    <input id="supplier" name="supplier" maxlength="255" value="{{ old('supplier') }}">
                </div>
            @endif
        </div>

        <div class="supply-search" @click.outside="pickerOpen = false" @keydown.escape.prevent.stop="$refs.search.focus(); pickerOpen = false">
            <label for="find-supply">Find a supply</label>
            <div class="supply-picker-control">
                <input x-ref="search" id="find-supply" x-model="search" @focus="openPicker()" @input="pickerOpen = true" @input.debounce.250ms="lookup()" @keydown.arrow-down.prevent="focusResult()" placeholder="Type a name or browse supplies" autocomplete="off" maxlength="255" aria-controls="supply-search-results">
                <button type="button" class="ui-button supply-picker-toggle" @click="togglePicker()" aria-label="Toggle supply dropdown" aria-controls="supply-search-results" :aria-expanded="pickerOpen">
                    <x-icon path="m6 9 6 6 6-6" />
                </button>
            </div>
            
            <div class="supply-picker-panel" x-ref="panel" x-show="pickerOpen" x-cloak>
                <p class="supply-picker-status" role="status" x-show="loading">Searching…</p>
                <div class="supply-picker-empty" x-show="!loading && searched && !results.length">
                    <p class="supply-picker-empty-text">
                        <span x-show="search.trim()">No supplies found matching <strong x-text="`“${search.trim()}”`"></strong>.</span>
                        <span x-show="!search.trim()">No matching supplies found.</span>
                    </p>
                    @if($type === 'receipt')
                        <button type="button" class="supply-picker-create-action" @click="openNewSupply()">
                            <x-icon name="plus" />
                            <span x-text="search.trim() ? `Create “${search.trim()}” as new supply` : 'Create a new supply'"></span>
                        </button>
                    @endif
                </div>

                <ul x-ref="results" id="supply-search-results" class="supply-results" aria-label="Available supplies" x-show="!loading && results.length">
                    <template x-for="supply in results" :key="supply.id">
                        <li>
                            <button type="button" @click="add(supply, $event)" :disabled="lines.some(line => Number(line.supply_id) === supply.id)">
                                <span class="supply-result-name" x-text="supply.supply_name"></span>
                                <span class="supply-result-stock" x-text="quantity(supply.current_quantity, supply.unit) + ' on hand'"></span>
                                <span class="supply-result-action" x-text="lines.some(line => Number(line.supply_id) === supply.id) ? 'Added' : '+ Add'"></span>
                            </button>
                        </li>
                    </template>
                </ul>

                <div class="supply-picker-footer" x-show="!loading && results.length">
                    <p class="supply-picker-footnote">Select several supplies, then enter quantities below.</p>
                    @if($type === 'receipt')
                        <button type="button" class="supply-picker-footer-create" @click="openNewSupply()">
                            <x-icon name="plus" />
                            <span x-text="search.trim() ? `Not in list? Add “${search.trim()}”` : 'Add new supply'"></span>
                        </button>
                    @endif
                </div>
            </div>
            <p class="form-hint" role="status" x-show="notice" x-text="notice" x-cloak></p>
        </div>

        <p class="field-error" role="alert" x-show="error" x-text="error" x-cloak></p>

        <p class="form-hint" x-show="type === 'usage' && previewLoading" role="status" x-cloak>Checking entries…</p>
        <div class="inventory-stock-lines" aria-label="Stock operation rows">
            <template x-for="(line,index) in lines" :key="line.supply_id">
                <section class="inventory-stock-line" :class="{'is-receipt': type === 'receipt'}">
                    <div class="inventory-stock-name">
                        <strong x-text="line.name" class="inventory-stock-title"></strong>
                        <div class="inventory-row-meta">
                            <span x-text="line.category"></span>
                            <template x-if="type === 'usage'">
                                <span x-text="' · ' + quantity(line.usable_quantity, line.unit) + ' usable'"></span>
                            </template>
                            <template x-if="type !== 'usage'">
                                <span x-text="' · ' + quantity(line.current_quantity, line.unit) + ' on hand'"></span>
                            </template>
                        </div>

                        <input type="hidden" :name="'lines['+index+'][supply_id]'" :value="line.supply_id">
                        <input type="hidden" :name="'lines['+index+'][expected_version]'" :value="line.expected_version">
                        <p class="field-error" x-text="fieldError(index, 'supply_id')"></p>
                    </div>
                    <div x-show="!entryMode(line)" class="inventory-stock-field">
                        <label class="inventory-stock-label" :for="'stock-quantity-'+line.supply_id" x-text="type === 'stocktake' ? 'Counted quantity' : 'Quantity'"></label>
                        <div class="stock-quantity-input">
                            <input :id="'stock-quantity-'+line.supply_id" :name="'lines['+index+'][quantity]'" :aria-invalid="!!fieldError(index,'quantity')" :aria-describedby="'quantity-error-'+line.supply_id" type="number" inputmode="decimal" :min="type === 'stocktake' ? 0 : 0.01" max="99999999.99" step="0.01" :required="!entryMode(line)" :disabled="entryMode(line)" x-model="line.quantity" @input="changed()" @input.debounce.350ms="preview()">
                            <span x-text="line.unit"></span>
                        </div>
                    </div>
                    <div x-show="type === 'receipt'" class="inventory-stock-field">
                        <template x-if="type === 'receipt' && line.category === 'ingredients'"><div>
                            <label class="inventory-stock-label" :for="'expiry-'+line.supply_id">Expiration date</label>
                            <input type="date" :id="'expiry-'+line.supply_id" :name="'lines['+index+'][expiry_date]'" required x-model="line.expiry_date" :aria-invalid="!!fieldError(index,'expiry_date')" :aria-describedby="'expiry-error-'+line.supply_id">
                            <p class="field-error" :id="'expiry-error-'+line.supply_id" x-text="fieldError(index,'expiry_date')"></p>
                        </div></template>
                        <p class="form-hint inventory-stock-packaging-note" x-show="line.category === 'packaging'">Expiration: Not applicable</p>
                    </div>
                    <div class="inventory-stock-actions">
                        <button type="button" class="ui-button quiet" :aria-label="'Remove '+line.name" @click="remove(index)">Remove</button>
                        <button type="button" class="ui-button quiet" x-show="type === 'stocktake'" @click="refreshLine(line)">Reload stock</button>
                    </div>
                    <div class="inventory-entry-inputs" x-show="entryMode(line)">
                        <p class="form-hint" x-text="type === 'waste' ? 'Enter quantities to discard from specific entries.' : 'Count every remaining entry, including expired and unknown stock.'"></p>
                        <template x-for="(entry,entryIndex) in line.entries" :key="entry.stock_entry_id"><div class="inventory-entry-input">
                            <label :for="'entry-'+entry.stock_entry_id" x-text="'#'+entry.stock_entry_id+' · '+(entry.expiry_date || (line.category === 'packaging' ? 'No expiry applicable' : 'Expiry unknown'))+' · '+quantity(entry.remaining_quantity,line.unit)+' remaining'"></label>
                            <div class="stock-quantity-input">
                            <input type="hidden" :disabled="!entryMode(line)" :name="'lines['+index+'][entries]['+entryIndex+'][stock_entry_id]'" :value="entry.stock_entry_id">
                                <input type="number" :id="'entry-'+entry.stock_entry_id" :disabled="!entryMode(line)" :name="'lines['+index+'][entries]['+entryIndex+'][quantity]'" :required="type === 'stocktake' && entryMode(line)" :value="entry.quantity === '' && type === 'waste' ? 0 : entry.quantity" @input="entry.quantity = $event.target.value" min="0" :max="type === 'waste' ? entry.remaining_quantity : 99999999.99" step="0.01" inputmode="decimal" placeholder="0">
                                <span x-text="line.unit"></span>
                            </div>
                            <p class="field-error" x-text="fieldError(index,'entries.'+entryIndex+'.quantity')"></p>
                        </div></template>
                        <p class="form-hint" x-show="!line.entries.length">No remaining entries. Record new dated stock through Stock in.</p>
                        <input type="hidden" :disabled="!entryMode(line)" :name="'lines['+index+'][quantity]'" :value="entryTotal(line)">
                    </div>
                    <p class="field-error inventory-line-feedback" :id="'quantity-error-'+line.supply_id" x-text="fieldError(index,'quantity')"></p>
                    <p class="form-hint inventory-line-feedback" x-show="type === 'usage' && line.allocations.length" x-text="allocationText(line)"></p>
                </section>
            </template>
            <p class="empty-state" x-show="!lines.length">Choose supplies above to add the first row.</p>
        </div>

        <div>
            <label for="notes" x-text="['waste', 'stocktake'].includes(type) ? 'Reason / explanation' : 'Notes (optional)'"></label>
            <textarea id="notes" name="notes" rows="2" maxlength="2000" :required="['waste', 'stocktake'].includes(type)">{{ old('notes') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="button" class="ui-button" x-show="type === 'usage'" @click="preview()" :disabled="previewLoading">Refresh allocation</button>
            <span x-text="lines.length + ' supplies · all rows save together'"></span>
            <a class="ui-button" data-cancel @click="window.__suppressUnload = true" href="{{ route('supplies.index') }}">Cancel</a>
            <button class="ui-button primary" :disabled="!lines.length || (type === 'usage' && !previewReady)" type="submit" x-text="type === 'receipt' ? 'Save stock in' : type === 'stocktake' ? 'Save stock count' : 'Save stock out'">{{ $type === 'receipt' ? 'Save stock in' : 'Save stock out' }}</button>
            <span role="status" data-submit-status></span>
        </div>
    </form>

    @if($type === 'receipt')
        <dialog x-ref="newSupplyDialog" class="supply-create-dialog" aria-labelledby="new-supply-title" @cancel="if (creating) $event.preventDefault()">
            <form x-ref="newSupplyForm" class="workspace-form" @submit.prevent="createSupply($event)">
                <div>
                    <h2 id="new-supply-title">Add a new supply</h2>
                    <p class="form-hint">Create it once with zero stock. Its quantity goes in the Stock in form.</p>
                </div>
                @csrf
                <input type="hidden" name="current_quantity" value="0">
                <input type="hidden" name="is_active" value="1">
                <div class="supply-definition-fields" data-supply-definition x-data="supplyDefinition(@js(['supply_name' => '', 'category' => 'ingredients', 'unit' => 'kg']))" @reset-definition="resetDefinition($event.detail)">
                    @include('admin.supplies.definition-fields', ['prefix' => 'new', 'supply' => new \App\Models\Supply])
                </div>
                <div>
                    <label for="new-reorder-level">Reorder level</label>
                    <input id="new-reorder-level" name="reorder_level" type="number" min="0" max="99999999.99" step="0.01" required value="0">
                    <p class="form-hint">Low-stock threshold, in the selected stock unit.</p>
                </div>
                <p class="field-error" role="alert" x-show="createError" x-text="createError" x-cloak></p>
                <div class="form-actions">
                    <button type="button" class="ui-button" :disabled="creating" @click="$refs.newSupplyDialog.close()">Cancel</button>
                    <button type="submit" class="ui-button primary" :disabled="creating" x-text="creating ? 'Adding…' : 'Create & add to stock in'">Create & add to stock in</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
@endsection
