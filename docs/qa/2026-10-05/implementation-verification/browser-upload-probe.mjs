import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 const page=await browser.newPage({viewport:{width:390,height:844}});
 page.on('pageerror',e=>console.log('JS',e.message));
 page.on('response',async r=>{if(r.url().includes('/draft/'))console.log(JSON.stringify({status:r.status(),body:(await r.text()).replace(/bakery-package-drafts[^" ]+/g,'[draft-path]')}));});
 await page.goto('http://127.0.0.1:8124/',{waitUntil:'networkidle'});
 await page.locator('a[aria-label^="Select "]').first().click();await page.waitForLoadState('networkidle');
 await page.locator('input[type=file]').setInputFiles({name:'design-reference.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII=','base64')});
 await page.waitForTimeout(2500);
 console.log(JSON.stringify(await page.locator('form[data-package-editor]').evaluate(e=>({uploading:e._x_dataStack[0].uploading,error:e._x_dataStack[0].error,uploadStatus:e._x_dataStack[0].uploadStatus,images:e._x_dataStack[0].items[0].staged_images?.length}))));
 await page.screenshot({path:path.join(directory,'evidence/upload-diagnostic.png'),fullPage:true});
 const baseline=await page.goto('http://127.0.0.1:8125/login',{waitUntil:'load'});console.log(JSON.stringify({baselineStatus:baseline.status(),body:(await page.locator('body').innerText()).slice(0,1400)}));
}finally{await browser.close();}
