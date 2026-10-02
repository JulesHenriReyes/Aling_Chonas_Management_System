import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = globalThis;
await import('../../public/js/catalog-order.js');
await import('../../public/js/staff-customer-picker.js');

test('package price responds to layer, quantity and extras', () => {
    const products = [{ id: 1, product_name: 'Cake', options: [
        { id: 10, price: 800 }, { id: 11, price: 1200 },
    ], add_ons: [{ id: 20, price: 100 }] }];
    const form = window.catalogOrder(products, [], '/order/quote');
    form.items = [{ product_id: 1, package_option_id: 10, quantity: 2, add_ons: [] }];
    assert.equal(form.total(), 1600);
    form.items[0].package_option_id = 11;
    assert.equal(form.total(), 2400);
    form.toggleExtra(form.items[0], products[0].add_ons[0], true);
    assert.equal(form.total(), 2500);
    form.selectedExtra(form.items[0], 20).quantity = 3;
    assert.equal(form.total(), 2700);
});

test('customer search matches name and phone and selection uses an explicit record', () => {
    const customers = [
        { id: 1, name: 'Maria De la Cruz', phone: '09171234567' },
        { id: 2, name: 'Ana Santos', phone: '09179998888' },
    ];
    const picker = window.staffCustomerPicker(customers, '', '/customers/inline', 'csrf');
    picker.search = 'de la';
    assert.deepEqual(picker.matches.map(customer => customer.id), [1]);
    picker.search = '999';
    assert.deepEqual(picker.matches.map(customer => customer.id), [2]);
    picker.choose(customers[0]);
    assert.equal(picker.selectedId, '1');
    assert.match(picker.search, /Maria De la Cruz/);
});

test('inline customer creation selects the returned customer', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (_url, options) => {
        assert.equal(options.method, 'POST');
        assert.deepEqual(JSON.parse(options.body), {
            first_name: 'María', middle_name: '', last_name: 'De la Cruz', phone_number: '09171234567',
        });
        return { ok: true, json: async () => ({ id: 3, full_name: 'María De la Cruz', phone_number: '09171234567' }) };
    };
    try {
        const picker = window.staffCustomerPicker([], '', '/orders/create/customer', 'csrf');
        picker.newCustomer = { first_name: 'María', middle_name: '', last_name: 'De la Cruz', phone_number: '09171234567' };
        await picker.createCustomer();
        assert.equal(picker.selectedId, '3');
        assert.equal(picker.search, 'María De la Cruz · 09171234567');
        assert.equal(picker.showAdd, false);
    } finally {
        globalThis.fetch = originalFetch;
    }
});
