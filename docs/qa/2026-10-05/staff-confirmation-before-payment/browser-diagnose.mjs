import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const browser = await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const page = await browser.newPage({viewport:{width:390,height:844}});
const failures = [], errors = [];
page.on('requestfailed', request => failures.push({url:request.url(),error:request.failure().errorText}));
page.on('pageerror', error => errors.push(error.message));
const fixture = JSON.parse(fs.readFileSync(new URL('./verification/evidence/preview-fixture.json',import.meta.url)));
await page.goto('http://127.0.0.1:8134'+(process.argv[2] === 'layout' ? fixture.public.approved : '/login'), {waitUntil:'networkidle'});
if (process.argv[2] === 'checkout') {
    await page.locator('#email').fill('owner@implementation.test');await page.locator('#password').fill('preview-test-only');
    await Promise.all([page.waitForNavigation(),page.locator('button[type="submit"]').click()]);
    await page.goto(fixture.url+'/orders/create');
    const token=await page.locator('input[name="_token"]').first().inputValue();
    await page.request.post(fixture.url+'/orders/create/details',{form:{_token:token,'items[0][product_id]':String(fixture.product),'items[0][package_option_id]':String(fixture.option),'items[0][quantity]':'1'}});
    await page.goto(fixture.url+'/orders/create/details',{waitUntil:'networkidle'});
}
if (['layout','checkout'].includes(process.argv[2])) {
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => document.documentElement.style.fontSize='200%');
    console.log(JSON.stringify(await page.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,
        overflowing:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1 && e.getBoundingClientRect().width>0)
            .slice(0,25).map(e=>({tag:e.tagName,classes:e.className,text:e.innerText?.substring(0,60),width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right}))})),null,2));
    await browser.close();
    process.exit(0);
}
console.log(JSON.stringify({url:page.url(),failures,errors,...await page.evaluate(() => ({title:document.title,tailwind:typeof window.tailwind,alpine:typeof window.Alpine,xdata:document.querySelectorAll('[x-data]').length,text:document.body.innerText.substring(0,800),scripts:[...document.scripts].map(s=>s.src)}))},null,2));
await page.screenshot({path:new URL('./verification/evidence/browser-login-diagnostic.png',import.meta.url).pathname.slice(1),fullPage:true});
await browser.close();
