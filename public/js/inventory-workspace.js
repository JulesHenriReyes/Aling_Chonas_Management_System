window.stockOperation = (type, initialLines, lookupUrl) => ({
    type, lines: initialLines, search: '', results: [], loading: false, searched: false, error: '', sequence: 0,
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
    add(supply) {
        if (this.lines.length >= 100) { this.error = 'Use at most 100 supplies per operation.'; return; }
        if (this.lines.some(line => Number(line.supply_id) === supply.id)) return;
        this.lines.push({supply_id:supply.id, name:supply.supply_name, unit:supply.unit, current_quantity:supply.current_quantity, expected_version:supply.stock_version, quantity:''});
        this.results = []; this.searched = false; this.search = '';
        this.$nextTick(() => document.getElementById('stock-quantity-'+supply.id)?.focus());
        this.$root.querySelector('form')?.dispatchEvent(new Event('input', {bubbles:true}));
    },
    delta(line) { const qty = Number(line.quantity || 0); return this.type === 'stocktake' ? qty - Number(line.current_quantity) : ['usage','waste'].includes(this.type) ? -qty : qty; },
    after(line) { return Number(line.current_quantity) + this.delta(line); },
    format(value, signed = false) { return Number(value).toLocaleString('en-PH',{minimumFractionDigits:2, maximumFractionDigits:2, signDisplay:signed ? 'exceptZero' : 'auto'}); },
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
