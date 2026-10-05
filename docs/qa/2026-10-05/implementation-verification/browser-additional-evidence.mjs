import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url));
const fixture=JSON.parse(fs.readFileSync(path.join(directory,'evidence/preview-fixture.json')));
const baseline=JSON.parse(fs.readFileSync(path.join(directory,'evidence/visual-baseline.json')));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records=[];
async function settle(page){await page.waitForFunction(()=>window.tailwind&&(!document.querySelector('[x-data]')||window.Alpine));await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(100);}
async function capture(page,role,state,width,environment){
    const filename=`${environment==='Regular application read-only smoke'?'regular':environment.startsWith('Original')?'baseline':'supplement'}-${role}-${state}-${width}.png`;
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
    await page.screenshot({path:path.join(directory,'evidence/screenshots',filename),fullPage:true});
    records.push({role,state,width,height:844,environment,engine:environment.startsWith('Regular')?'regular MariaDB':'isolated SQLite',browser:browser.version(),overflow,url:page.url().replace(/[a-f0-9]{64}/g,'[synthetic-token-redacted]'),screenshot:'screenshots/'+filename});
    if(!environment.startsWith('Original')) assert(overflow<=1,filename+' page overflow');
}
try{
    const contexts={};
    for(const role of ['owner','assistant']){
        const context=await browser.newContext({viewport:{width:390,height:844}});contexts[role]=context;
        const page=await context.newPage();await page.goto(fixture.url+'/login',{waitUntil:'load'});await settle(page);
        await page.locator('[name=email]').fill(role+'@implementation.test');await page.locator('[name=password]').fill('preview-test-only');
        await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);await settle(page);
        for(const width of [360,390,430,768,1024,1440]){
            await page.setViewportSize({width,height:844});
            if(role==='owner')for(const [state,url] of Object.entries({'reports-refund-only':'/reports?mode=month&month=2027-01','report-refund-records':'/reports/records?mode=month&month=2027-01&metric=refunds_completed'})){
                const response=await page.goto(fixture.url+url,{waitUntil:'load'});assert.equal(response.status(),200);await settle(page);await capture(page,role,state,width,'Synthetic isolated SQLite future-period fixture');
            }
            for(const [state,url] of Object.entries({orders:'/orders',customers:'/customers','order-pending':'/orders/'+fixture.orders.pending,'order-confirmed':'/orders/'+fixture.orders.confirmed,'order-ready':'/orders/'+fixture.orders.ready})){
                const response=await page.goto('http://127.0.0.1:8125'+url,{waitUntil:'load'});assert.equal(response.status(),200);await settle(page);
                await capture(page,role,state,width,'Original tracked presentation reference; current isolated read controllers; '+baseline.revision);
            }
        }
        await context.close();
    }
    const guestContext=await browser.newContext({viewport:{width:390,height:844}});const guest=await guestContext.newPage();
    for(const width of [360,390,430,768,1024,1440]){
        await guest.setViewportSize({width,height:844});
        for(const state of ['verified','cancelled','failure-pending']){
            const response=await guest.goto('http://127.0.0.1:8125'+fixture.public[state],{waitUntil:'load'});assert.equal(response.status(),200);await settle(guest);await capture(guest,'guest','payment-'+state,width,'Original tracked presentation reference; current isolated read controllers; '+baseline.revision);
        }
    }
    await guestContext.close();
    const regularContext=await browser.newContext({viewport:{width:390,height:844}});const regular=await regularContext.newPage();
    for(const [state,url] of Object.entries({catalog:'/',login:'/login','internal-guest-denied':'/orders'})){
        const response=await regular.goto('http://127.0.0.1:8000'+url,{waitUntil:'load'});assert.equal(response.status(),200);await settle(regular);
        if(state==='internal-guest-denied')assert(regular.url().endsWith('/login'));
        await capture(regular,'guest',state,390,'Regular application read-only smoke');
    }
    await regularContext.close();
    fs.writeFileSync(path.join(directory,'evidence/browser-additional-evidence.json'),JSON.stringify({outcome:'All response, route, styling and non-baseline overflow assertions passed',baselineLimit:baseline.purpose,records},null,2));
    console.log(JSON.stringify({outcome:'PASS',screenshots:records.length,baseline:records.filter(r=>r.environment.startsWith('Original')).length,regularReadOnly:3}));
}finally{await browser.close();}
