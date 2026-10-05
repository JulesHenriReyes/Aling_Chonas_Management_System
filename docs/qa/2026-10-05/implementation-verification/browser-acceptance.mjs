import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(directory, 'evidence/preview-fixture.json')));
const widths = process.argv.slice(2).map(Number).filter(Boolean);
if (!widths.length) widths.push(360, 390, 430, 768, 1024, 1440);
const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records = [];
const failures = [];
const screenshots = path.join(directory, 'evidence/screenshots');
fs.mkdirSync(screenshots, {recursive: true});
async function settle(page) {
    await page.waitForFunction(() => window.tailwind && (!document.querySelector('[x-data]') || window.Alpine), null, {timeout: 15000});
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(100);
}
async function go(page, url, expected = 200) {
    const response = await page.goto(fixture.url + url, {waitUntil: 'load'});
    assert.equal(response.status(), expected, url.replace(/[a-f0-9]{64}/g, '[redacted]'));
    if (expected === 200) await settle(page);
}
async function capture(page, role, state, width, textScale = 1) {
    const filename = `${role}-${state}-${width}${textScale === 1 ? '' : '-text200'}.png`;
    const measurements = await page.evaluate(() => {
        const visible = e => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
        const controls = [...document.querySelectorAll('main button, main summary, main .ui-button, main .sort-heading, main .customer-row-actions a, [aria-controls="staff-navigation"]')]
            .filter(visible).map(e => { const box = e.getBoundingClientRect(); return {name: e.getAttribute('aria-label') || e.innerText?.trim().replace(/\s+/g, ' ').slice(0,80), tag:e.tagName, width:box.width, height:box.height}; });
        const tables = [...document.querySelectorAll('.table-scroll')].map(e => ({width:e.clientWidth, content:e.scrollWidth, localScroll:getComputedStyle(e).overflowX}));
        const grid = document.querySelector('.store-catalog');
        return {pageOverflow:document.documentElement.scrollWidth-innerWidth, controls, tables,
            catalogColumns:grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : null,
            activeElement:document.activeElement?.id, phoneErrors:[...document.querySelectorAll('#phone-error, [id*="error-phone_number"]')].filter(visible).map(e=>e.innerText)};
    });
    const url = page.url().replace(/[a-f0-9]{64}/g, '[synthetic-token-redacted]').replace(/[a-f0-9-]{36}/g, '[draft-key]');
    await page.screenshot({path:path.join(screenshots, filename), fullPage:true});
    const record = {role, state, width, height:844, textScale, url, environment:fixture.environment, database:path.basename(fixture.database), browser:browser.version(), screenshot:'screenshots/'+filename, ...measurements};
    records.push(record);
    if (record.pageOverflow > 1) failures.push({filename, issue:'page-overflow', amount:record.pageOverflow});
    if (state === 'catalog' && width <= 430 && textScale === 1 && record.catalogColumns !== 2) failures.push({filename, issue:'normal-phone-catalog-columns', columns:record.catalogColumns});
    for (const control of measurements.controls) if (control.width < 43.9 || control.height < 43.9) failures.push({filename, issue:'touch-target', ...control});
    return record;
}
async function login(context, role) {
    const page = await context.newPage();
    await go(page, '/login');
    await page.locator('[name=email]').fill(role+'@implementation.test');
    await page.locator('[name=password]').fill('preview-test-only');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
    await settle(page);
    return page;
}
async function drawer(page, role, width) {
    if (width >= 1024) return;
    const trigger = page.locator('[aria-controls="staff-navigation"]');
    await trigger.click();
    await page.waitForFunction(() => document.querySelector('[data-sidebar]').dataset.open === 'true');
    await page.waitForTimeout(250);
    assert(await page.evaluate(() => document.querySelector('[data-sidebar]').contains(document.activeElement)), 'Drawer entry focus');
    const focusables = page.locator('[data-sidebar] a, [data-sidebar] button').filter({visible:true});
    await focusables.last().focus();
    await page.keyboard.press('Tab');
    assert(await page.locator('[data-sidebar-close]').evaluate(e => e === document.activeElement), 'Drawer Tab wrap');
    await page.keyboard.press('Shift+Tab');
    assert(await focusables.last().evaluate(e => e === document.activeElement), 'Drawer reverse Tab wrap');
    await capture(page, role, 'navigation-open', width);
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.querySelector('[data-sidebar]').dataset.open === 'false');
    assert(await trigger.evaluate(e => e === document.activeElement), 'Drawer restores trigger focus');
    assert.equal(await page.evaluate(() => document.body.style.overflow), '', 'Drawer restores scroll');
    records.push({role, width, state:'drawer-keyboard', entryFocus:true, tabWrap:true, reverseTabWrap:true, escape:true, focusRestored:true, scrollRestored:true});
}
async function publicDraft(page, width) {
    await go(page, '/');
    await capture(page, 'guest', 'catalog', width);
    for (let index = 0; index < 2; index++) {
        await page.locator('a[aria-label^="Select "]').nth(index).click();
        await settle(page);
        await page.locator('[name$="[themes]"]').fill('Blue flowers · package '+(index+1));
        await page.locator('[name$="[special_request]"]').fill('A long design instruction with two lines.\nPlease keep the name clearly readable.');
        if (index === 0) await capture(page, 'guest', 'customize', width);
        await Promise.all([page.waitForURL(/\/?saved_line=/), page.getByRole('button', {name:'Save package to order'}).click()]);
        await settle(page);
    }
    await capture(page, 'guest', 'catalog-multiline', width);
    await go(page, '/order/details');
    await page.locator('[name=first_name]').fill('Preview');
    await page.locator('[name=last_name]').fill('Contact');
    await page.locator('[name=phone_number]').fill('+63 32 234 5678');
    const minimum = await page.locator('[name=pickup_date]').getAttribute('min');
    await page.locator('[name=pickup_date]').fill(minimum);
    await page.locator('[name=pickup_time]').fill('15:00');
    await capture(page, 'guest', 'contact-landline', width);
    await page.reload({waitUntil:'load'}); await settle(page);
    assert.equal(await page.locator('[name=phone_number]').inputValue(), '+63 32 234 5678', 'Contact refresh preserved');
    await page.locator('[name=phone_number]').fill('0917ABC4567');
    await Promise.all([page.waitForNavigation({waitUntil:'load'}), page.getByRole('button', {name:'Submit order & continue'}).click()]);
    await settle(page);
    assert.equal(await page.locator('[name=phone_number]').inputValue(), '0917ABC4567');
    assert.equal(await page.locator('[name=first_name]').inputValue(), 'Preview');
    assert.equal(await page.locator('[name=phone_number]').getAttribute('aria-invalid'), 'true');
    await capture(page, 'guest', 'contact-error', width);
    await Promise.all([page.waitForURL(fixture.url+'/'), page.getByRole('button', {name:'Back to packages',exact:true}).click()]);
    await settle(page);
    assert.equal(await page.locator('.bag-line').count(), 2, 'Back preserves both package lines');
    await go(page, '/order/details');
    assert.equal(await page.locator('[name=phone_number]').inputValue(), '0917ABC4567');
    records.push({role:'guest', width, state:'contact-draft-interaction', refresh:true, invalidInput:true, back:true, packageLines:2, dateMinimum:minimum});
}
try {
    for (const width of widths) {
        const guestContext = await browser.newContext({viewport:{width,height:844}});
        const guest = await guestContext.newPage();
        guest.on('pageerror', e => failures.push({width,role:'guest',issue:'pageerror',message:e.message}));
        await publicDraft(guest, width);
        for (const [state,url] of Object.entries(fixture.public)) { await go(guest,url); await capture(guest,'guest','payment-'+state,width); }
        for (const role of ['owner','assistant']) {
            const context = await browser.newContext({viewport:{width,height:844}});
            const page = await login(context, role);
            page.on('pageerror', e => failures.push({width,role,issue:'pageerror',message:e.message}));
            const routes = {
                dashboard:'/dashboard', pickups:'/pickup-schedule', 'pickups-empty':'/pickup-schedule?date=2026-11-30',
                orders:'/orders', customers:'/customers', 'customer-detail':'/customers/'+fixture.customer,
                supplies:'/supplies', 'supply-detail':'/supplies/'+fixture.supply, 'stock-history':'/inventory/history',
                'stock-receipt':'/inventory/create/receipt', 'stock-usage':'/inventory/create/usage', 'stocktake':'/inventory/create/stocktake',
                expenses:'/expenses', 'expense-create':'/expenses/create', 'expense-edit':'/expenses/'+fixture.expense+'/edit',
                'expense-detail':'/expenses/'+fixture.expense, 'expense-history':'/expenses/history'};
            if (role === 'owner') Object.assign(routes, {
                'customer-create':'/customers/create', 'customer-edit':'/customers/'+fixture.customer+'/edit',
                'order-create':'/orders/create', reports:'/reports?mode=month&month=2026-10',
                'reports-empty':'/reports?mode=month&month=2026-08', 'report-records':'/reports/records?mode=month&month=2026-10&metric=cancellation_income'});
            for (const [state,url] of Object.entries(routes)) { await go(page,url); await capture(page,role,state,width); }
            for (const [state,id] of Object.entries(fixture.orders)) { await go(page,'/orders/'+id); await capture(page,role,'order-'+state,width); }
            if (role === 'assistant') {
                for (const [state,url] of Object.entries({'reports-denied':'/reports','customer-create-denied':'/customers/create','order-create-denied':'/orders/create'})) {
                    await go(page,url,403); await capture(page,role,state,width);
                }
                await go(page,'/orders/'+fixture.orders.ready);
                assert.equal(await page.locator('form[action$="complete-pickup"]').count(),0);
                assert.equal(await page.locator('a[href$="/reports"]').count(),0);
            }
            await go(page,'/supplies'); await drawer(page,role,width);
            if (width === 390) {
                for (const [state,url] of Object.entries({supplies:'/supplies',expenses:'/expenses','order-ready':'/orders/'+fixture.orders.ready})) {
                    await go(page,url); await page.addStyleTag({content:'html {font-size: 200% !important;}'}); await capture(page,role,state,width,2);
                }
            }
            await context.close();
        }
        if (width === 390) { await go(guest,'/'); await guest.addStyleTag({content:'html {font-size:200% !important;}'}); await capture(guest,'guest','catalog',width,2); }
        await guestContext.close();
        fs.writeFileSync(path.join(directory, `evidence/browser-grid-${width}.json`), JSON.stringify({records:records.filter(r=>r.width===width),failures:failures.filter(f=>f.width===width || f.filename?.includes('-'+width))},null,2));
        console.log(JSON.stringify({width,screenshots:records.filter(r=>r.width===width&&r.screenshot).length, failures:failures.filter(f=>f.width===width||f.filename?.includes('-'+width)).length}));
    }
    fs.writeFileSync(path.join(directory,'evidence/browser-grid-'+widths.join('-')+'-summary.json'), JSON.stringify({records:records.length,failures},null,2));
    console.log(JSON.stringify({records:records.length,failures:failures.length}));
} finally { await browser.close(); }
