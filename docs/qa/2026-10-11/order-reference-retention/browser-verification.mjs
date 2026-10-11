import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(dir, 'preview-fixture.json')));
const base = 'http://127.0.0.1:8151';
const baseline = process.argv.includes('--baseline');
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
const customer = await browser.newPage({viewport:{width:1440,height:900}});
const staff = await browser.newPage({viewport:{width:1440,height:900}});
const errors = [];
const checks = [];
for (const page of [customer, staff]) page.on('pageerror', error => errors.push(error.message));
function check(ok, message) { if (!ok) throw new Error(message); checks.push(message); }
async function ready(page) {
    await page.waitForFunction(() => !!window.Alpine && typeof tailwind !== 'undefined');
    await page.evaluate(() => document.fonts.ready);
}
async function capture(page, name) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({top:0,behavior:'instant'}); });
    await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});
}
async function layout(page, label) {
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth+1);
    const broken = await page.locator('img').evaluateAll(images => images.filter(img => img.complete && !img.naturalWidth).length);
    check(broken === 0, label+': no broken images');
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1), label+': no horizontal overflow');
}
try {
    await staff.goto(base+'/login'); await ready(staff);
    await staff.locator('[name=email]').fill('owner@example.test');
    await staff.locator('[name=password]').fill('password123');
    await Promise.all([staff.waitForURL(url => !url.pathname.endsWith('/login')), staff.getByRole('button',{name:'Sign In to Staff System',exact:true}).click()]);
    for (const width of [1440,390]) {
        const viewport = {width,height:width===1440?900:844};
        await customer.setViewportSize(viewport); await staff.setViewportSize(viewport);
        for (const kind of baseline ? ['photos'] : ['photos','history','hold']) {
            await customer.goto(base+'/order/payment/'+fixture.orders[kind].token); await ready(customer);
            await staff.goto(base+'/orders/'+fixture.orders[kind].id); await ready(staff);
            await layout(customer,kind+' customer '+width); await layout(staff,kind+' staff '+width);
            if (!baseline && kind==='photos') {
                check((await customer.locator('body').innerText()).includes('Reference photos expired'), 'Customer sees photo expiration');
                check((await staff.locator('body').innerText()).includes('Reference photos expired'), 'Staff sees photo expiration');
                check((await customer.locator('#design-change').innerText()).includes('different package'), 'Decline reason stays visible');
                await customer.locator('.design-history > summary').click();
                await customer.locator('.design-history-entry > summary').click();
                check((await customer.locator('.design-comparison-list').innerText()).includes('Reference photos expired'), 'Historical photo preview shows expiry instead of an image');
            }
            if (!baseline && kind==='history') {
                check((await customer.locator('#design-change').innerText()).includes('Design-change history expired'), 'Customer sees history expiration');
                check((await staff.locator('#design-change').innerText()).includes('Design-change history expired'), 'Staff sees history expiration');
            }
            await capture(customer,(baseline?'baseline':'customer')+'-'+kind+'-'+width);
            await capture(staff,(baseline?'baseline-staff':'staff')+'-'+kind+'-'+width);
        }
    }
    if (!baseline) {
        for (const kind of ['recent','active','cancelled','hold']) {
            await customer.goto(base+'/order/payment/'+fixture.orders[kind].token); await ready(customer); await layout(customer,kind);
            check(await customer.locator('a[href*="/references/"]').count() > 0, kind+': reference photo retained');
        }
        await staff.goto(base+'/orders'); await ready(staff);
        const button = staff.getByRole('button',{name:new RegExp('View order '+fixture.orders.photos.number)});
        // The list response also powers the order drawer; verify JSON directly.
        const response = await staff.request.get(base+'/orders/'+fixture.orders.photos.id,{headers:{Accept:'application/json'}});
        const data = await response.json();
        check(data.order.photos_expired && data.order.images.length===0, 'Expired photos are omitted from drawer JSON');
    }
    check(errors.length===0, 'No JavaScript errors');
    fs.writeFileSync(path.join(dir,baseline?'baseline-results.json':'browser-results.json'),JSON.stringify({checks,errors},null,2));
    console.log(JSON.stringify({checks:checks.length,errors}));
} finally { await browser.close(); }
