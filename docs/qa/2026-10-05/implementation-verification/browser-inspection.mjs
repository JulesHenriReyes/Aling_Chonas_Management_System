import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url));
const fixture=JSON.parse(fs.readFileSync(path.join(directory,'evidence/preview-fixture.json')));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 const page=await browser.newPage({viewport:{width:390,height:844}});
 await page.goto(fixture.url+'/login',{waitUntil:'networkidle'});await page.locator('[name=email]').fill('owner@implementation.test');await page.locator('[name=password]').fill('preview-test-only');
 await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
 await page.goto(fixture.url+'/orders/'+fixture.orders.ready,{waitUntil:'networkidle'});
 await page.locator('form[action$="complete-pickup"]').locator('..').screenshot({path:path.join(directory,'evidence/pickup-panel-390.png')});
 await page.goto(fixture.url+'/reports?mode=month&month=2027-01',{waitUntil:'networkidle'});
 await page.screenshot({path:path.join(directory,'evidence/refund-only-viewport-390.png')});
 await page.goto(fixture.url+fixture.public['failure-pending'],{waitUntil:'networkidle'});
 await page.screenshot({path:path.join(directory,'evidence/public-refund-pending-viewport-390.png')});
}finally{await browser.close();}
