import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url)), evidence=path.join(directory,'verification/evidence');
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'preview-fixture.json')));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records=[], interactions=[], failures=[], pages={};
const screenshots=path.join(evidence,'screenshots-checkout-'+Date.now());fs.mkdirSync(screenshots,{recursive:true});
const safe=url=>url.replace(/[a-f0-9]{64}/g,'[private-token-redacted]');
async function settle(page) {
    await page.waitForFunction(()=>window.tailwind && (!document.querySelector('[x-data]') || window.Alpine));
    await page.evaluate(()=>document.fonts.ready); await page.waitForTimeout(100);
}
async function go(page,url) {const response=await page.goto(fixture.url+url,{waitUntil:'load'});assert.equal(response.status(),200);await settle(page);}
async function capture(role,state,width,scale=1) {
    const page=pages[role]; if(scale===2) await page.evaluate(()=>document.documentElement.style.fontSize='200%');
    await page.evaluate(()=>document.querySelectorAll('input[readonly]').forEach(e=>{if(e.value.includes('/order/payment/'))e.value='[Private link redacted]';}));
    const facts=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,interLoaded:document.fonts.check('16px Inter'),
        buttons:[...document.querySelectorAll('main button[type="submit"]')].filter(e=>e.getClientRects().length).map(e=>({text:e.innerText.trim(),width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height}))}));
    const filename=`${role}-${state}-${width}${scale===2?'-text200':''}.png`;
    await page.screenshot({path:path.join(screenshots,filename),fullPage:true});
    records.push({role,state,width,height:844,scale,browser:browser.version(),url:safe(page.url()),screenshot:`${path.basename(screenshots)}/${filename}`,...facts});
    if(facts.overflow>1 || !facts.interLoaded) failures.push({filename,...facts});
    for(const button of facts.buttons) if(button.width<43.5||button.height<43.5) failures.push({filename,button});
}
function qrConfig(mode) {const result=spawnSync('C:/xampp/php/php.exe',[path.join(directory,'preview-settings.php'),mode],{encoding:'utf8'});assert.equal(result.status,0,result.stdout+result.stderr);}
try {
    for(const role of ['public','owner']) {
        const context=await browser.newContext({viewport:{width:390,height:844},timezoneId:'Asia/Manila'});
        const page=pages[role]=await context.newPage();
        if(role==='owner') {
            await go(page,'/login'); await page.locator('#email').fill('owner@implementation.test');await page.locator('#password').fill('preview-test-only');
            await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('button[type="submit"]').click()]);await settle(page);
        }
        await go(page,role==='owner'?'/orders/create':'/');
        if(role==='public') {
            const packageLink=await page.locator(`.store-catalog a[href*="/packages/${fixture.product}/customize/"]`).getAttribute('href');
            await go(page,new URL(packageLink).pathname);
        }
        const token=await page.locator('input[name="_token"]').first().inputValue();
        const response=await page.request.post(fixture.url+(role==='owner'?'/orders/create/details':'/order/details'),{form:{_token:token,
            'items[0][product_id]':String(fixture.product),'items[0][package_option_id]':String(fixture.option),'items[0][quantity]':'1',
            'items[0][themes]':'Blue buttercream flowers','items[0][special_request]':'Happy birthday, Maria!'}});
        assert.equal(response.status(),200);assert.match(response.url(),/\/details$/);
    }
    for(const width of [360,390,430,768,1024,1440]) for(const role of ['public','owner']) {
        await pages[role].setViewportSize({width,height:844});await go(pages[role],role==='owner'?'/orders/create/details':'/order/details');
        assert.match(await pages[role].locator('main').innerText(),/Pay only after staff confirms your request/);
        await capture(role,'checkout',width);
        if(width===390) await capture(role,'checkout',width,2);
    }
    for(const width of [360,390,430,768,1024,1440]) {
        const page=pages.public;await page.setViewportSize({width,height:844});await go(page,'/');
        assert.match(await page.locator('.store-progress').innerText(),/4\. Staff review/);await capture('public','catalog-review',width);
        const packageLink=await page.locator(`.store-catalog a[href*="/packages/${fixture.product}/customize/"]`).getAttribute('href');
        await go(page,new URL(packageLink).pathname);assert.match(await page.locator('.store-progress').innerText(),/4\. Staff review/);
        await capture('public','customize-review',width);
    }
    const buyer=pages.public;await buyer.setViewportSize({width:390,height:844});await go(buyer,'/order/details');
    await buyer.locator('#first-name').fill('Maria');await buyer.locator('#last-name').fill('Buyer');await buyer.locator('#phone').fill('12345');
    const pickup=new Date(Date.now()+3*86400000).toISOString().slice(0,10);
    await buyer.locator('#pickup-date').fill(pickup);await buyer.locator('#pickup-time').fill('15:00');await buyer.locator('#notes').fill('Keep the saved specifications.');
    await Promise.all([buyer.waitForNavigation({waitUntil:'load'}),buyer.locator('form.order-details-grid').evaluate(form=>HTMLFormElement.prototype.submit.call(form))]);await settle(buyer);
    assert.equal(await buyer.locator('#first-name').inputValue(),'Maria');assert.equal(await buyer.locator('#phone').inputValue(),'12345');
    assert.match(await buyer.locator('#phone-error').innerText(),/number/i);assert.equal(await buyer.locator('#phone').getAttribute('aria-invalid'),'true');
    assert.equal(await buyer.locator('#phone').evaluate(e=>e===document.activeElement),true);
    assert.match(await buyer.locator('main').innerText(),/Blue buttercream flowers/);await capture('public','checkout-error',390);
    interactions.push({case:'Server validation retains draft/contact data, associates phone error, focuses invalid field',passed:true});
    await buyer.locator('#phone').fill('09171234567');
    await Promise.all([buyer.waitForNavigation({waitUntil:'load'}),buyer.locator('form.order-details-grid button[type="submit"]').first().click()]);await settle(buyer);
    assert.match(await buyer.locator('main').innerText(),/Awaiting staff confirmation/);
    assert.equal(await buyer.locator('input[name="receipt"]').count(),0);await capture('public','submitted-request',390);
    interactions.push({case:'Corrected customer submission opens saved private status with review first and payment withheld',passed:true});
    qrConfig('disable');
    for(const width of [390,1440]) {
        await buyer.setViewportSize({width,height:844});await go(buyer,fixture.public.approved);
        assert.match(await buyer.locator('main').innerText(),/Payment details are not configured yet/);
        assert.equal(await buyer.locator('img[alt="Business GCash payment QR"]').count(),0);
        assert.equal(await buyer.locator('input[name="receipt"]').count(),0);
        await capture('public','missing-settings',width);
    }
    interactions.push({case:'Approved order without configured QR has no blind payment or receipt invitation',passed:true});
    assert.equal(failures.length,0,JSON.stringify(failures));
    console.log(JSON.stringify({captures:records.length,interactions:interactions.length,failures:failures.length}));
} finally {
    qrConfig('restore');fs.writeFileSync(path.join(evidence,'browser-checkout.json'),JSON.stringify({records,interactions,failures},null,2));await browser.close();
}
