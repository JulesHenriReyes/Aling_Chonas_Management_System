import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const evidence = path.join(directory, 'verification/evidence');
const fixture = JSON.parse(fs.readFileSync(path.join(evidence, 'preview-fixture.json')));
const screenshots = path.join(evidence, 'screenshots-complete');
fs.mkdirSync(screenshots, {recursive: true});
const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records = [], failures = [], interactions = [];
const interactionsOnly = process.argv.includes('--interactions');
const contexts = {}, pages = {};
const safe = value => value.replace(/[a-f0-9]{64}/g, '[private-token-redacted]');

async function settle(page) {
    await page.waitForFunction(() => window.tailwind && (!document.querySelector('[x-data]') || window.Alpine), null, {timeout: 20000});
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(100);
}
async function go(page, url) {
    const response = await page.goto(fixture.url + url, {waitUntil: 'load'});
    assert.equal(response.status(), 200, safe(url));
    await settle(page);
}
async function capture(role, state, width, scale = 1) {
    const page = pages[role];
    if (scale === 2) await page.evaluate(() => document.documentElement.style.fontSize = '200%');
    await page.evaluate(() => {
        document.querySelectorAll('input[readonly]').forEach(input => {
            if (input.value.includes('/order/payment/')) input.value = '[Private order link redacted for evidence]';
        });
    });
    const measured = await page.evaluate(() => {
        const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
        const controls = [...document.querySelectorAll('main form[action*="/confirm"] button, main form[action*="/decline"] button, main form[action*="/receipt"] button, main form[action*="/reconcile-refund"] button')]
            .filter(visible).map(element => { const box=element.getBoundingClientRect(); return {name:element.innerText.trim(), width:box.width, height:box.height}; });
        return {overflow: document.documentElement.scrollWidth-innerWidth, controls,
            font:getComputedStyle(document.body).fontFamily, interLoaded:document.fonts.check('16px Inter'),
            qrVisible:[...document.querySelectorAll('img[alt="Business GCash payment QR"]')].some(visible),
            receiptFormVisible:[...document.querySelectorAll('input[name="receipt"]')].some(visible),
            preparationAvailable:[...document.querySelectorAll('input[name="status"][value="preparing"]')].some(element => visible(element.closest('form')))};
    });
    const filename = `${role}-${state}-${width}${scale === 2 ? '-text200' : ''}.png`;
    await page.screenshot({path:path.join(screenshots,filename),fullPage:true});
    records.push({role,state,width,height:844,scale,url:safe(page.url()),browser:browser.version(),screenshot:`screenshots-complete/${filename}`,...measured});
    if (measured.overflow > 1) failures.push({filename,issue:'page-overflow',amount:measured.overflow});
    for (const control of measured.controls) if (control.width < 43.5 || control.height < 43.5) failures.push({filename,issue:'small-workflow-control',control});
    if (!measured.interLoaded) failures.push({filename,issue:'font-not-loaded'});
    if (role === 'public') {
        if (['pending','declined','legacy','awaiting','verified'].includes(state) && (measured.qrVisible || measured.receiptFormVisible)) failures.push({filename,issue:'payment-controls-ineligible'});
        if (['approved','rejected'].includes(state) && (!measured.qrVisible || !measured.receiptFormVisible)) failures.push({filename,issue:'eligible-controls-missing'});
    }
    if (state === 'approved' && role !== 'public' && measured.preparationAvailable) failures.push({filename,issue:'unpaid-preparation-enabled'});
}
async function submitAndReload(page, button) {
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),button.click()]);
    await settle(page);
}

try {
    for (const role of ['public','owner','assistant']) {
        contexts[role] = await browser.newContext({viewport:{width:390,height:844},timezoneId:'Asia/Manila'});
        pages[role] = await contexts[role].newPage();
        if (role !== 'public') {
            await go(pages[role],'/login');
            await pages[role].locator('input[name="email"]').fill(`${role}@implementation.test`);
            await pages[role].locator('input[name="password"]').fill('preview-test-only');
            await submitAndReload(pages[role],pages[role].locator('button[type="submit"]'));
        }
    }
    if (!interactionsOnly) for (const width of [360,390,430,768,1024,1440]) {
        for (const page of Object.values(pages)) await page.setViewportSize({width,height:844});
        for (const state of ['pending','approved','awaiting','rejected','verified','declined','legacy']) {
            await go(pages.public,fixture.public[state]);
            await capture('public',state,width);
        }
        for (const role of ['owner','assistant']) {
            for (const state of ['pending','approved','confirmed','declined','legacy']) {
                await go(pages[role],`/orders/${fixture.orders[state]}`);
                await capture(role,state,width);
            }
            for (const queue of ['review','deposit','receipts','booked']) {
                await go(pages[role],`/orders?queue=${queue}`);
                await capture(role,`queue-${queue}`,width);
            }
            await go(pages[role],'/dashboard'); await capture(role,'dashboard',width);
            await go(pages[role],'/pickup-schedule'); await capture(role,'schedule',width);
        }
        if ([390,1440].includes(width)) {
            for (const state of ['cancelled','failure-pending','failure-completed']) {
                await go(pages.public,fixture.public[state]); await capture('public',state,width);
            }
        }
        if (width === 390) {
            await go(pages.public,fixture.public.pending); await capture('public','pending',width,2);
            await go(pages.public,fixture.public.approved); await capture('public','approved',width,2);
            for (const role of ['owner','assistant']) {
                await go(pages[role],`/orders/${fixture.orders.pending}`); await capture(role,'pending',width,2);
                await go(pages[role],'/pickup-schedule'); await capture(role,'schedule',width,2);
            }
        }
        fs.writeFileSync(path.join(evidence,'browser-grid.json'),JSON.stringify({records,failures},null,2));
        console.log(JSON.stringify({width,captures:records.length,failures:failures.length}));
    }

    const owner=pages.owner, assistant=pages.assistant, buyer=pages.public;
    await go(owner,`/orders/${fixture.orders.pending}`);
    const confirm=owner.locator('form[action$="/confirm"]');
    await confirm.locator('button').click();
    assert.equal(await confirm.locator('input[name="feasibility_confirmed"]').evaluate(element=>element.validity.valueMissing),true);
    await Promise.all([owner.waitForNavigation({waitUntil:'load'}),confirm.evaluate(form=>HTMLFormElement.prototype.submit.call(form))]);
    await settle(owner);
    assert.match(await owner.locator('[data-error-summary]').innerText(),/feasibility confirmed/i);
    assert.equal(await confirm.locator('input[name="feasibility_confirmed"]').evaluate(element=>element===document.activeElement),true);
    assert.equal(await confirm.locator('input[name="feasibility_confirmed"]').getAttribute('aria-invalid'),'true');
    await confirm.locator('input[name="feasibility_confirmed"]').focus();
    await owner.keyboard.press('Space');
    assert.equal(await confirm.locator('input[name="feasibility_confirmed"]').isChecked(),true);
    await submitAndReload(owner,confirm.locator('button'));
    assert.match(await owner.locator('main').innerText(),/awaiting deposit/i);
    assert.equal(await owner.locator('input[name="status"][value="preparing"]').count(),0);
    interactions.push({case:'Owner confirmation acknowledgment and unpaid preparation guard',passed:true});

    await go(assistant,`/orders/${fixture.public_ids.pending}`);
    assert.equal(await assistant.locator('form[action$="/decline"]').count(),0);
    const staffConfirm=assistant.locator('form[action$="/confirm"]');
    await staffConfirm.locator('input[name="feasibility_confirmed"]').check();
    await submitAndReload(assistant,staffConfirm.locator('button'));
    await go(buyer,fixture.public.pending);
    assert.equal(await buyer.locator('input[name="receipt"]').count(),1);
    await buyer.locator('input[name="reference_number"]').fill('BROWSER-REVIEW-DEPOSIT');
    await buyer.locator('input[name="receipt"]').setInputFiles({name:'receipt.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII=','base64')});
    await submitAndReload(buyer,buyer.locator('form[action$="/receipt"] button[type="submit"]'));
    assert.match(await buyer.locator('main').innerText(),/awaiting verification/i);
    assert.equal(await buyer.locator('input[name="receipt"]').count(),0);
    interactions.push({case:'Assistant confirms public request, buyer submits receipt, repeat payment withheld',passed:true});

    await go(owner,`/orders/${fixture.public_ids.pending}`);
    const accept=owner.locator('form[action*="/payment-proofs/"][action$="/accept"]');
    await accept.locator('input[name="amount"]').fill('1000');
    await accept.locator('input[name="account_checked"]').check();
    await submitAndReload(owner,accept.locator('button'));
    assert.equal(await owner.locator('input[name="status"][value="preparing"]').count(),1);
    await go(buyer,fixture.public.pending);
    assert.match(await buyer.locator('main').innerText(),/booking is secured/i);
    interactions.push({case:'Owner verification unlocks preparation and updates buyer booking status',passed:true});

    await go(owner,`/orders/${fixture.orders.legacy}`);
    const decline=owner.locator('form[action$="/decline"]');
    await decline.locator('..').locator('summary').click();
    await decline.locator('textarea[name="decline_reason"]').fill('Capacity unavailable; the reported transfer was not received.');
    await decline.locator('input[name="no_funds_checked"]').check();
    await submitAndReload(owner,decline.locator('button'));
    await go(buyer,fixture.public.legacy);
    assert.match(await buyer.locator('main').innerText(),/Request declined/);
    assert.equal(await buyer.locator('input[name="receipt"]').count(),0);
    interactions.push({case:'Owner account investigation and declined request preserve payment lock',passed:true});

    fs.writeFileSync(path.join(evidence,'browser-interactions.json'),JSON.stringify(interactions,null,2));
    assert.equal(failures.length,0,JSON.stringify(failures.slice(0,8)));
    console.log(JSON.stringify({captures:interactionsOnly ? JSON.parse(fs.readFileSync(path.join(evidence,'browser-grid.json'))).records.length : records.length,interactions:interactions.length,failures:failures.length}));
} finally {
    if (!interactionsOnly) fs.writeFileSync(path.join(evidence,'browser-grid.json'),JSON.stringify({records,failures},null,2));
    await browser.close();
}
