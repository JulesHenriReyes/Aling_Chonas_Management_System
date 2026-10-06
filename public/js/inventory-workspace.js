window.supplyDefinition = initial => ({
    definition: {...initial},
    resetDefinition(name = '') {
        this.definition = {supply_name: name, category: 'ingredients', unit: 'kg'};
    }
});

window.stockOperation = (type, initialLines, lookupUrl, createUrl, csrfToken) => ({
    type, lines: initialLines, search: '', results: [], loading: false, searched: false, error: '', sequence: 0,
    pickerOpen: false, creating: false, createError: '', notice: '',
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
        if ((next === 'stocktake') !== (this.type === 'stocktake')) {
            // A quantity used is a reduction; a count is the remaining total.
            // Require re-entry instead of silently reinterpreting the same number.
            this.lines.forEach(line => { line.quantity = ''; });
            this.error = 'Stock entry method changed. Enter the quantities again for the selected method.';
        }
        this.type = next;
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
        this.lines.push({supply_id:supply.id, name:supply.supply_name, unit:supply.unit, current_quantity:supply.current_quantity, expected_version:supply.stock_version, quantity:''});
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
            line.quantity = ''; this.error = 'Current stock reloaded for '+line.name+'. Recount and enter the actual quantity.';
        } catch { this.error = 'Could not reload this supply. It may now be inactive. Please review inventory.'; }
    }
});
