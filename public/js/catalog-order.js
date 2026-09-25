window.catalogOrder = function (products, previousItems, quoteUrl, staff = false) {
    return {
        products, staff, items: [], sequence: 0, quote: null, busy: false, error: '',
        init() {
            this.items = Object.values(previousItems || {}).map(item => ({
                ...item, uid: ++this.sequence, draft_key: item.draft_key || this.newKey(), add_ons: Object.values(item.add_ons || {}),
            }));
            this.$watch('items', () => { this.quote = null; this.error = ''; });
        },
        product(item) { return this.products.find(product => String(product.id) === String(item.product_id)); },
        option(item) { return this.product(item)?.options.find(option => String(option.id) === String(item.package_option_id)); },
        money(amount) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount); },
        newKey() { return window.crypto?.randomUUID?.() || String(Date.now()) + '-' + String(Math.random()); },
        add(product) {
            this.items.push({ uid: ++this.sequence, draft_key: this.newKey(), product_id: product.id, package_option_id: product.options[0]?.id || '', quantity: 1, themes: '', special_request: '', add_ons: [] });
            this.$nextTick(() => document.getElementById('option-' + this.sequence)?.focus());
        },
        selectedExtra(item, id) { return item.add_ons.find(extra => String(extra.add_on_id) === String(id)); },
        toggleExtra(item, extra, enabled) {
            if (enabled) item.add_ons.push({ add_on_id: extra.id, quantity: 1 });
            else item.add_ons = item.add_ons.filter(selected => String(selected.add_on_id) !== String(extra.id));
        },
        lineTotal(item) {
            let cents = Math.round(Number(this.option(item)?.price || 0) * 100) * Number(item.quantity || 0);
            for (const selected of item.add_ons) {
                const extra = this.product(item)?.add_ons.find(extra => String(extra.id) === String(selected.add_on_id));
                cents += Math.round(Number(extra?.price || 0) * 100) * Number(selected.quantity || 0);
            }
            return cents / 100;
        },
        total() { return this.items.reduce((total, item) => total + this.lineTotal(item), 0); },
        async submit(event) {
            if (this.busy) return;
            if (!this.items.length) { this.error = 'Add at least one cake package.'; return; }
            if (this.quote) { this.busy = true; event.target.submit(); return; }
            this.busy = true;
            this.error = '';
            const signature = JSON.stringify(this.items);
            try {
                const response = await fetch(quoteUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': event.target.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ items: this.items.map(({ uid, images, ...item }) => item) }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The total could not be checked. Please try again.');
                if (signature !== JSON.stringify(this.items)) throw new Error('Your selection changed. Review the total again.');
                this.quote = result;
                this.$nextTick(() => this.$refs.review.focus());
            } catch (error) {
                this.error = error.message || 'The total could not be checked. Please try again.';
            } finally { this.busy = false; }
        },
    };
};
