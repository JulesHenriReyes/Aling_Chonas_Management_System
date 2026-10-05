import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url)), evidence=path.join(directory,'verification/evidence');
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'preview-fixture.json'))), records=[];
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const page=await browser.newPage({viewport:{width:390,height:844}});
async function settle(){await page.waitForFunction(()=>window.tailwind && (!document.querySelector('[x-data]')||window.Alpine));await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(100);}
try {
    await page.goto(fixture.url+'/login',{waitUntil:'load'});await settle();await page.locator('#email').fill('owner@implementation.test');await page.locator('#password').fill('preview-test-only');
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('button[type="submit"]').click()]);await settle();
    for(const width of [360,390,430,768,1024,1440]) {
        await page.setViewportSize({width,height:844});const response=await page.goto(fixture.url+`/orders/${fixture.orders.approved}`,{waitUntil:'load'});assert.equal(response.status(),200);await settle();
        const text=await page.locator('main').innerText();assert.match(text,/Verify 50% Deposit and Secure Booking/);assert.doesNotMatch(text,/automatically confirm|Down Payment to Confirm/);
        assert.equal(await page.locator('input[name="status"][value="preparing"]').count(),0);
        const facts=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,interLoaded:document.fonts.check('16px Inter')}));
        assert.ok(facts.overflow<=1&&facts.interLoaded);
        const screenshot=`screenshots-complete/owner-approved-current-${width}.png`;await page.screenshot({path:path.join(evidence,screenshot),fullPage:true});
        records.push({role:'owner',state:'approved',width,height:844,scale:1,browser:browser.version(),url:page.url(),screenshot,...facts});
    }
    fs.writeFileSync(path.join(evidence,'browser-deposit-current.json'),JSON.stringify({records,failures:[]},null,2));console.log(JSON.stringify({captures:records.length,failures:0}));
} finally {await browser.close();}
