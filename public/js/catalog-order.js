window.catalogOrder = function (products, previousItems, quoteUrl, staff = false, persistKey = null, unavailable = false) {
    return {
        products, staff, unavailable, items: [], sequence: 0, quote: null, busy: false, error: '', uploading:false, uploadStatus:'', fieldErrors:{}, saveTimer:null, savedSignature:'',
        init() {
            this.items = Object.values(previousItems || {}).map(item => ({
                ...item, uid: ++this.sequence, draft_key: item.draft_key || this.newKey(), add_ons: Object.values(item.add_ons || {}),
            }));
            this.savedSignature = this.editorSignature();
            if (persistKey) {
                try {
                    const saved = JSON.parse(sessionStorage.getItem(persistKey) || 'null');
                    if (saved && !Object.keys(JSON.parse(document.getElementById('validation-errors')?.textContent || '{}')).length && String(saved.product_id) === String(this.items[0]?.product_id)) {
                        Object.assign(this.items[0], {package_option_id:saved.package_option_id, quantity:saved.quantity, themes:saved.themes, special_request:saved.special_request, add_ons:saved.add_ons || []});
                    }
                } catch {}
                this.$el.addEventListener('change', event => {
                    if (event.target.type === 'file' || event.target.name?.includes('remove_staged_images')) this.saveReferences();
                });
                window.addEventListener('beforeunload', event => { if (this.uploading) { event.preventDefault(); event.returnValue=''; } });
                window.addEventListener('pageshow', () => { this.busy=false; });
            }
            this.$watch('items', () => {
                this.quote = null;
                if (!persistKey) this.error = '';
                if (persistKey) try { sessionStorage.setItem(persistKey, JSON.stringify(this.items[0])); } catch {}
                if (staff && persistKey && this.editorSignature() !== this.savedSignature) this.scheduleSave();
            });
            if (staff && persistKey && this.editorSignature() !== this.savedSignature) this.scheduleSave();
        },
        editorSignature() {
            return JSON.stringify(this.items.map(({package_option_id, quantity, themes, special_request, add_ons}) => ({package_option_id, quantity, themes, special_request, add_ons})));
        },
        scheduleSave() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => { if (!this.uploading && !this.busy) this.saveReferences(); }, 400);
        },
        async saveReferences() {
            if (this.uploading) return;
            clearTimeout(this.saveTimer);
            this.uploading=true; this.error=''; this.uploadStatus='';
            const signature=this.editorSignature();
            const form=this.$el, data=new FormData(form);
            const files=[...form.querySelectorAll('input[type=file]')];
            files.forEach(input => input.disabled=true);
            try {
                const response=await fetch(form.dataset.editorUrl,{method:'POST', body:data, headers:{Accept:'application/json'}});
                const result=await response.json();
                this.fieldErrors=result.errors || {};
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'Reference photos could not be saved.');
                this.items[0].staged_images=result.staged_images;
                files.forEach(input => input.value='');
                this.uploadStatus='Reference photos saved with this package draft.';
                this.savedSignature=signature;
            } catch(error) { this.error=error.message; }
            finally { files.forEach(input => input.disabled=false); this.uploading=false; if (staff && !this.error && signature !== this.editorSignature()) this.scheduleSave(); }
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
        decreaseExtra(item, extra) {
            const selected = this.selectedExtra(item, extra.id);
            if (!selected) return;
            const current = Number(selected.quantity || 1);
            if (current > 1) {
                selected.quantity = current - 1;
            } else {
                this.toggleExtra(item, extra, false);
            }
        },
        increaseExtra(item, extra) {
            const selected = this.selectedExtra(item, extra.id);
            if (!selected) {
                this.toggleExtra(item, extra, true);
            } else {
                selected.quantity = Math.min(999, Number(selected.quantity || 1) + 1);
            }
        },
        setExtraQuantity(item, extra, val) {
            const selected = this.selectedExtra(item, extra.id);
            if (!selected) return;
            let num = parseInt(val, 10);
            if (isNaN(num) || num < 1) num = 1;
            if (num > 999) num = 999;
            selected.quantity = num;
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
                    body: JSON.stringify({ items: this.items.map(({ uid, images, ...item }) => item), draft_id:event.target.querySelector('[name="draft_id"]')?.value }),
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
