import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = globalThis;
await import('../../public/js/package-inclusions.js');
await import('../../public/js/catalog-order.js');

test('owner can select distinct reusable items, edit quantities, remove and re-add', () => {
    const editor = window.packageInclusions([{ id: 1 }, { id: 2 }], [{ add_on_id: 1, quantity: 6 }]);
    editor.init();
    editor.add();
    assert.deepEqual(editor.rows.map(row => Number(row.add_on_id)), [1, 2]);
    assert.equal(editor.used('1', editor.rows[1]), true);
    assert.equal(editor.used('1', editor.rows[0]), false);
    editor.rows[0].quantity = 8;
    editor.add();
    assert.equal(editor.rows.length, 2);
    editor.rows.splice(1, 1);
    editor.add();
    assert.equal(editor.rows[0].quantity, 8);
    assert.equal(editor.rows[1].add_on_id, 2);
});

test('included catalog item has no surcharge and same-item paid extras apply once per line', () => {
    const form = window.catalogOrder([{
        id: 1, options: [{ id: 10, price: 1000, included_items: [{ id: 5, price: 50, pivot: { quantity: 6 } }] }],
        add_ons: [{ id: 5, price: 50 }],
    }], [], '/order/quote');
    form.items = [{ product_id: 1, package_option_id: 10, quantity: 3, add_ons: [{ add_on_id: 5, quantity: 2 }] }];
    assert.equal(form.total(), 3100);
    form.items[0].quantity = 5;
    assert.equal(form.total(), 5100);
    form.items[0].add_ons = [];
    assert.equal(form.total(), 5000);
});
