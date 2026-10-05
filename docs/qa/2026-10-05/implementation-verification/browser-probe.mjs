import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const label = process.argv[2] || 'before';
const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    page.on('pageerror', error => console.log('PAGE ERROR:', error.message));
    await page.goto('http://127.0.0.1:8124/login', {waitUntil: 'networkidle', timeout: 25000});
    await page.locator('[name=email]').fill('owner@implementation.test');
    await page.locator('[name=password]').fill('preview-test-only');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
    const records = [];
    for (const url of ['/orders', '/supplies', '/customers']) {
        await page.goto('http://127.0.0.1:8124'+url, {waitUntil:'networkidle'});
        const controls = await page.locator('button,summary,main a').evaluateAll(elements => elements
            .filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden')
            .map(el => {const r=el.getBoundingClientRect();return {tag:el.tagName, text:el.textContent.trim().replace(/\s+/g,' ').slice(0,70), label:el.getAttribute('aria-label'), class:el.className, width:r.width,height:r.height};}));
        records.push({url,width:390,overflow:await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth),controls});
        await page.screenshot({path:path.join(directory,'evidence',url.slice(1)+'-'+label+'.png'),fullPage:true});
    }
    fs.writeFileSync(path.join(directory,'evidence','controls-'+label+'.json'),JSON.stringify(records,null,2));
    console.log(JSON.stringify(records.map(r=>({url:r.url,overflow:r.overflow,small:r.controls.filter(c=>(c.tag==='BUTTON'||c.tag==='SUMMARY'||c.text==='View'||c.text==='Edit'||c.class==='sort-heading')&&(c.width<44 ||c.height<44))})),null,2));
} finally { await browser.close(); }
