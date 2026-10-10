import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/bakery-ui.js', import.meta.url), 'utf8');
const start = source.indexOf('    // 1. Orders page AJAX filtering');
const end = source.indexOf('    // 2. Pickup Schedule page AJAX filtering', start);

function setup() {
    const listeners = {};
    const classList = { add() {}, remove() {} };
    const scroll = { scrollLeft: 70, scrollTop: 20 };
    const table = { innerHTML: 'old orders', classList, contains: () => false,
        setAttribute() {}, removeAttribute() {}, querySelector: () => scroll };
    const form = { contains: () => false, querySelector: () => null, addEventListener() {} };
    const meta = { innerHTML: '0 orders' };
    const nodes = { 'orders-filter-form': form, 'orders-table-container': table, 'orders-results-meta': meta };
    const document = { hidden: false, activeElement: null, getElementById: id => nodes[id] || null,
        querySelectorAll: () => [], addEventListener: (name, fn) => { listeners[name] = fn; } };
    const location = { href: 'http://localhost/orders?origin=public&page=2', pathname: '/orders' };
    let calls = 0;
    let tick;
    let result = 'new order';
    let pending;
    const context = { document, window: { location, history: { pushState() { throw new Error('Background changed history'); } },
        addEventListener: (name, fn) => { listeners[name] = fn; } }, AbortController, URL, console,
        setInterval: (fn, ms) => { assert.equal(ms, 5000); tick = fn; return 1; }, clearInterval() {},
        clearTimeout() {}, setTimeout() {},
        fetch: async (url, options) => {
            calls++;
            assert.equal(url, location.href);
            assert.equal(options.cache, 'no-store');
            if (pending) await pending;
            if (result === 'error') throw new Error('Offline');
            return { ok: true, text: async () => result };
        },
        DOMParser: class { parseFromString(html) { return { getElementById: id => {
            if (html === 'login') return null;
            return id === 'orders-table-container' ? { innerHTML: html } :
                id === 'orders-results-meta' ? { innerHTML: '1 order' } : null;
        } }; } },
    };
    vm.runInNewContext(source.slice(start, end), context);
    return { document, form, table, meta, scroll, location, listeners, tick: () => tick(), calls: () => calls,
        result: value => { result = value; }, pending: value => { pending = value; } };
}

test('new orders update automatically with filters, page, count and scroll preserved', async () => {
    const app = setup();
    await app.tick();
    assert.equal(app.table.innerHTML, 'new order');
    assert.equal(app.meta.innerHTML, '1 order');
    assert.equal(app.scroll.scrollLeft, 70);
    assert.equal(app.location.href, 'http://localhost/orders?origin=public&page=2');
});

test('hidden tabs, focused filters and open dialogs pause polling; visible tab refreshes', async () => {
    const app = setup();
    app.document.hidden = true;
    await app.tick();
    app.document.hidden = false;
    app.form.contains = () => true;
    await app.tick();
    app.form.contains = () => false;
    app.document.querySelectorAll = () => [{ getClientRects: () => [1] }];
    await app.tick();
    assert.equal(app.calls(), 0);
    app.document.querySelectorAll = () => [];
    await app.listeners.visibilitychange();
    // The visibility listener starts an async update without awaiting it.
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(app.table.innerHTML, 'new order');
});

test('offline or expired sessions leave the list usable and retry without navigation', async () => {
    const app = setup();
    for (const result of ['error', 'login']) {
        app.result(result);
        await app.tick();
        assert.equal(app.table.innerHTML, 'old orders');
        assert.equal(app.location.href, 'http://localhost/orders?origin=public&page=2');
    }
    app.result('new order');
    await app.tick();
    assert.equal(app.table.innerHTML, 'new order');
});

test('requests do not overlap and responses cannot replace a list during interaction', async () => {
    const app = setup();
    let finish;
    app.pending(new Promise(resolve => { finish = resolve; }));
    const first = app.tick();
    await app.tick();
    assert.equal(app.calls(), 1);
    app.form.contains = () => true;
    finish();
    await first;
    assert.equal(app.table.innerHTML, 'old orders');
    app.form.contains = () => false;
    await app.tick();
    assert.equal(app.table.innerHTML, 'new order');
});
