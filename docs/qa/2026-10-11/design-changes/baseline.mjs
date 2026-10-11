import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const fixture = JSON.parse(fs.readFileSync(new URL('./preview-fixture.json', import.meta.url)));
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
const page = await browser.newPage();
for (const [label, viewport] of [['desktop', {width:1440,height:900}], ['mobile', {width:390,height:844}]]) {
    await page.setViewportSize(viewport);
    await page.goto('http://127.0.0.1:8148/order/payment/'+fixture.orders.deposit.token);
    await page.locator('#fixed-total').waitFor();
    await page.screenshot({path:new URL('./baseline-'+label+'.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true});
}
await browser.close();
console.log('Baseline desktop and mobile captured.');
