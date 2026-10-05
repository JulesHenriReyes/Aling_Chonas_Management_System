import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url));
const fixture=JSON.parse(fs.readFileSync(path.join(directory,'evidence/preview-fixture.json')));
const completed=JSON.parse(fs.readFileSync(path.join(directory,'evidence/completed-public-preview.json')));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records=[];
try{
 const page=await browser.newPage({viewport:{width:390,height:844}});
 for(const width of [360,390,430,768,1024,1440]){
  await page.setViewportSize({width,height:844});
  for(const [state,url] of Object.entries({cancelled:fixture.public.cancelled,'failure-pending':fixture.public['failure-pending'],'failure-completed':fixture.public['failure-completed'],completed:completed.public_url})){
   const response=await page.goto(fixture.url+url,{waitUntil:'networkidle'});assert.equal(response.status(),200);
   assert.equal(await page.getByText('Pay exactly 50% at booking.',{exact:false}).count(),0);
   if(state==='completed')assert(await page.getByText('Pickup is complete. No further payment is due.',{exact:true}).isVisible());
   else assert(await page.getByText('This order is cancelled. See the payment and refund status below.',{exact:true}).isVisible());
   assert.equal(await page.locator('input[type=file]').count(),0);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);assert(overflow<=1);
   const name=`guest-terminal-${state}-${width}.png`;await page.screenshot({path:path.join(directory,'evidence/screenshots',name),fullPage:true});
   records.push({role:'guest',state,width,height:844,textScale:1,engine:'isolated SQLite',browser:browser.version(),overflow,url:page.url().replace(/[a-f0-9]{64}/g,'[synthetic-token-redacted]'),screenshot:'screenshots/'+name});
  }
 }
 fs.writeFileSync(path.join(directory,'evidence/browser-terminal-states.json'),JSON.stringify({outcome:'All terminal status, no-payment-action and overflow assertions passed',records},null,2));
 console.log(JSON.stringify({outcome:'PASS',screenshots:records.length}));
}finally{await browser.close();}
