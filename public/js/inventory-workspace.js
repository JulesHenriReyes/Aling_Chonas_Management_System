window.supplyDefinition = initial => ({
    definition: {...initial},
    resetDefinition(name = '') {
        this.definition = {supply_name: name, category: 'ingredients', unit: 'kg'};
    }
});

window.stockOperation = (type, initialLines, lookupUrl, createUrl, csrfToken, previewUrl, initialErrors = {}) => ({
    type, lines: initialLines, search: '', results: [], loading: false, searched: false, error: '', sequence: 0,
    pickerOpen: false, creating: false, createError: '', notice: '', fieldErrors: initialErrors, previewSequence: 0, previewReady: false, previewLoading: false, businessDate: '',
    init() { this.lines.forEach(line => { line.allocations = []; line.expiry_date ??= ''; }); if (this.type === 'usage') this.preview(); },
    fieldError(index, field) { return (this.fieldErrors['lines.'+index+'.'+field] || []).join(' '); },
    entryMode(line) { return this.type === 'waste' || (this.type === 'stocktake' && line.category === 'ingredients'); },
    entryTotal(line) { return line.entries.reduce((total, entry) => total + Math.round(Number(entry.quantity || 0) * 100), 0) / 100; },
    changed() { this.previewReady = false; this.previewLoading = false; this.previewSequence++; this.lines.forEach(line => line.allocations = []); },
    remove(index) { this.lines.splice(index, 1); this.changed(); if (this.type === 'usage') this.preview(); },
    submit(event) { if (!this.lines.length || (this.type === 'usage' && !this.previewReady)) { event.preventDefault(); this.error = !this.lines.length ? 'Add at least one supply.' : 'Review the current allocation preview before saving.'; if (this.type === 'usage') this.preview(); } },
    async preview() {
        if (this.type !== 'usage') return;
        this.changed();
        if (!this.lines.length || this.lines.some(line => !(Number(line.quantity) > 0))) return;
        const sequence = ++this.previewSequence; this.previewLoading = true; this.error = ''; this.fieldErrors = {};
        try {
            const response = await fetch(previewUrl, {method:'POST', headers:{Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrfToken}, body:JSON.stringify({lines:this.lines.map(line => ({supply_id:line.supply_id, quantity:line.quantity}))})});
            const data = await response.json(); if (sequence !== this.previewSequence) return;
            if (!response.ok) { this.fieldErrors = data.errors || {}; throw new Error(data.message || 'Could not preview available stock.'); }
            this.businessDate = data.business_date;
            data.lines.forEach(result => { const line = this.lines.find(line => Number(line.supply_id) === result.supply_id); line.expected_version = result.expected_version; line.usable_quantity = result.available_quantity; line.current_quantity = result.on_hand_quantity; line.allocations = result.allocations; });
            this.previewReady = true;
        } catch (error) { if (sequence === this.previewSequence) this.error = error.message; }
        finally { if (sequence === this.previewSequence) this.previewLoading = false; }
    },
    allocationText(line) { return line.allocations.map((entry, index) => (index ? 'then ' : 'Uses ') + this.quantity(entry.quantity, line.unit) + (entry.expiry_date ? ' expiring '+entry.expiry_date : ' from oldest entry') + ' (#'+entry.stock_entry_id+')').join(', '); },
    async openPicker() {
        this.pickerOpen = true;
        if (!this.searched) await this.lookup();
    },
    togglePicker() {
        if (this.pickerOpen) this.pickerOpen = false;
        else this.openPicker();
    },
    focusResult() {
        this.openPicker().then(() => this.$nextTick(() => (this.$refs.panel?.querySelector('button:not(:disabled)') || this.$refs.results?.querySelector('button:not(:disabled)'))?.focus()));
    },
    changeType(next) {
        if (next === this.type) return;
        this.lines.forEach(line => { line.quantity = ''; line.entries.forEach(entry => entry.quantity = ''); });
        this.changed(); this.fieldErrors = {}; this.type = next;
        this.notice = 'Method changed. Enter quantities for the selected method.';
    },
    async lookup() {
        const sequence = ++this.sequence;
        this.loading = true;
        this.error = '';
        try {
            const response = await fetch(lookupUrl + '?q=' + encodeURIComponent(this.search), {headers: {'Accept':'application/json'}});
            if (!response.ok) throw new Error('Search unavailable. Try again.');
            const results = await response.json();
            if (sequence !== this.sequence) return;
            this.results = results; this.searched = true;
        } catch (error) { if (sequence === this.sequence) this.error = error.message; }
        finally { if (sequence === this.sequence) this.loading = false; }
    },
    add(supply, event) {
        if (this.lines.length >= 100) { this.error = 'Use at most 100 supplies per operation.'; return; }
        if (this.lines.some(line => Number(line.supply_id) === supply.id)) return;
        this.lines.push({supply_id:supply.id, name:supply.supply_name, category:supply.category, unit:supply.unit, current_quantity:supply.current_quantity, usable_quantity:supply.usable_quantity, expected_version:supply.stock_version, quantity:'', expiry_date:'', entries:supply.entries.map(entry => ({...entry, quantity:''})), allocations:[]}); this.changed();
        this.notice = supply.supply_name + ' added. Select another supply or enter its quantity below.';
        if (event?.detail === 0) this.$nextTick(() => (this.$refs.results.querySelector('button:not(:disabled)') || this.$refs.search).focus());
        this.$refs.stockForm.dispatchEvent(new Event('input', {bubbles:true}));
    },
    openNewSupply() {
        this.createError = '';
        this.$refs.newSupplyForm.reset();
        this.$refs.newSupplyForm.querySelector('[data-supply-definition]')
            .dispatchEvent(new CustomEvent('reset-definition', {detail: (this.search || '').trim()}));
        this.$refs.newSupplyDialog.showModal();
    },
    async createSupply(event) {
        if (this.creating) return;
        this.creating = true; this.createError = '';
        try {
            const response = await fetch(createUrl, {
                method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: new FormData(event.target)
            });
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Review the supply details.');
                throw new Error('Could not add the supply. Try again.');
            }
            this.add(data);
            this.$refs.newSupplyDialog.close();
            this.search = ''; this.searched = false;
            this.pickerOpen = true;
            await this.lookup();
            this.notice = data.supply_name + ' created with zero stock. Enter its quantity below, then save stock in.';
        } catch (error) {
            this.createError = error instanceof SyntaxError ? 'Could not add the supply. Refresh your sign-in and try again.' : error.message;
        } finally { this.creating = false; }
    },
    delta(line) { const qty = Number(line.quantity || 0); return this.type === 'stocktake' ? qty - Number(line.current_quantity) : ['usage','waste'].includes(this.type) ? -qty : qty; },
    after(line) { return Number(line.current_quantity) + this.delta(line); },
    format(value, signed = false) { return Number(value).toLocaleString('en-PH',{minimumFractionDigits:2, maximumFractionDigits:2, signDisplay:signed ? 'exceptZero' : 'auto'}); },
    quantity(value, unit, signed = false) { return this.format(value, signed) + ' ' + unit; },
    async refreshLine(line) {
        try {
            const response = await fetch(lookupUrl+'?q='+encodeURIComponent(line.name), {headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error();
            const supply = (await response.json()).find(item => item.id === Number(line.supply_id));
            if (!supply) throw new Error();
            line.current_quantity = supply.current_quantity; line.expected_version = supply.stock_version;
            line.usable_quantity = supply.usable_quantity; line.entries = supply.entries.map(entry => ({...entry, quantity:''})); line.quantity = ''; this.error = 'Current stock reloaded for '+line.name+'. Recount and enter the actual quantity.';
        } catch { this.error = 'Could not reload this supply. It may now be inactive. Please review inventory.'; }
    }
});
