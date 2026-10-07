window.staffCustomerPicker = function (customers, initialId, createUrl, csrfToken, initialPicker = {}) {
    return {
        customers, selectedId: initialId ? String(initialId) : '', search: '', open: false,
        showAdd: false, creating: false, error: '', fieldErrors: {}, activeIndex: -1,
        newCustomer: { first_name: '', middle_name: '', last_name: '', phone_number: '' },
        init() {
            this.restorePicker(initialPicker);
            const selected = this.customers.find(customer => String(customer.id) === this.selectedId);
            if (selected) this.search = selected.name + ' · ' + selected.phone;
            else this.selectedId='';
            if (typeof this.$watch === 'function') {
                this.$watch('newCustomer', () => this.notify());
                this.$watch('showAdd', () => this.notify());
            }
        },
        restorePicker(value) {
            for (const field of Object.keys(this.newCustomer)) if (typeof value[field] === 'string') this.newCustomer[field]=value[field];
            if (typeof value.search === 'string') this.search=value.search;
            this.showAdd=value.showAdd === '1';
        },
        snapshot() { return {...this.newCustomer, search:this.search, showAdd:this.showAdd ? '1' : '0'}; },
        notify() {
            this.$el?.dispatchEvent(new CustomEvent('staff-customer-change', {bubbles:true, detail:{customer_id:this.selectedId, customer_picker:this.snapshot()}}));
        },
        searchChanged() { this.selectedId=''; this.open=true; this.activeIndex=-1; this.notify(); },
        move(direction) {
            this.open=true;
            if (!this.matches.length) { this.activeIndex=-1; return; }
            this.activeIndex=this.activeIndex < 0 ? (direction > 0 ? 0 : this.matches.length-1)
                : (this.activeIndex + direction + this.matches.length) % this.matches.length;
            this.$nextTick?.(() => document.getElementById('customer-option-'+this.matches[this.activeIndex]?.id)?.scrollIntoView({block:'nearest'}));
        },
        selectActive() { if (this.open && this.matches[this.activeIndex]) this.choose(this.matches[this.activeIndex]); },
        get matches() {
            const query = this.search.trim().toLocaleLowerCase();
            return this.customers.filter(customer => !query || (customer.name + ' ' + customer.phone).toLocaleLowerCase().includes(query)).slice(0, 12);
        },
        choose(customer) {
            this.selectedId = String(customer.id);
            this.search = customer.name + ' · ' + customer.phone;
            this.open = false;
            this.error = '';
            this.activeIndex=-1;
            this.notify();
        },
        async createCustomer() {
            if (this.creating) return;
            this.creating = true;
            this.error = '';
            this.fieldErrors = {};
            try {
                const response = await fetch(createUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(this.newCustomer),
                });
                const data = await response.json().catch(() => ({}));
                this.fieldErrors = data.errors || {};
                if (response.status === 419) throw new Error('Your session expired. Reload this page before saving. Copy the new customer details first.');
                if (response.status === 403) throw new Error('Only the Owner can add customers. Your entered details are still here.');
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save customer.');
                const customer = { id: data.id, name: data.full_name, phone: data.phone_number };
                if (!this.customers.some(existing => String(existing.id) === String(customer.id))) this.customers.push(customer);
                this.choose(customer);
                this.showAdd = false;
                this.newCustomer = { first_name: '', middle_name: '', last_name: '', phone_number: '' };
            } catch (error) {
                this.error = error.message || 'Could not save customer.';
                const ids={first_name:'new-first', last_name:'new-last', middle_name:'new-middle', phone_number:'new-phone'};
                if (this.$el) document.getElementById(ids[Object.keys(this.fieldErrors)[0]])?.focus();
            }
            finally { this.creating = false; }
        },
    };
};
