window.staffCustomerPicker = function (customers, initialId, createUrl, csrfToken) {
    return {
        customers, selectedId: initialId ? String(initialId) : '', search: '', open: false,
        showAdd: false, creating: false, error: '',
        newCustomer: { first_name: '', middle_name: '', last_name: '', phone_number: '' },
        init() {
            const selected = this.customers.find(customer => String(customer.id) === this.selectedId);
            if (selected) this.search = selected.name + ' · ' + selected.phone;
        },
        get matches() {
            const query = this.search.trim().toLocaleLowerCase();
            return this.customers.filter(customer => !query || (customer.name + ' ' + customer.phone).toLocaleLowerCase().includes(query)).slice(0, 12);
        },
        choose(customer) {
            this.selectedId = String(customer.id);
            this.search = customer.name + ' · ' + customer.phone;
            this.open = false;
            this.error = '';
        },
        async createCustomer() {
            if (this.creating) return;
            this.creating = true;
            this.error = '';
            try {
                const response = await fetch(createUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(this.newCustomer),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save customer.');
                const customer = { id: data.id, name: data.full_name, phone: data.phone_number };
                this.customers.push(customer);
                this.choose(customer);
                this.showAdd = false;
                this.newCustomer = { first_name: '', middle_name: '', last_name: '', phone_number: '' };
            } catch (error) { this.error = error.message || 'Could not save customer.'; }
            finally { this.creating = false; }
        },
    };
};
