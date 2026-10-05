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
  const cases=Object.entries(fixture.public).map(([state,url])=>({state,url,port:8124,name:`guest-payment-${state}-${width}.png`,kind:'current'}));
  for(const state of ['cancelled','failure-pending','failure-completed','completed'])cases.push({state,url:state==='completed'?completed.public_url:fixture.public[state],port:8124,name:`guest-terminal-${state}-${width}.png`,kind:'current-terminal'});
  for(const state of ['verified','cancelled','failure-pending'])cases.push({state,url:fixture.public[state],port:8125,name:`baseline-guest-payment-${state}-${width}.png`,kind:'original presentation reference'});
  for(const item of cases){
   const response=await page.goto('http://127.0.0.1:'+item.port+item.url,{waitUntil:'networkidle'});assert.equal(response.status(),200);
   const redactedFields=await page.locator('input[readonly],textarea[readonly]').evaluateAll(elements=>{let count=0;for(const e of elements)if(/[a-f0-9]{64}/.test(e.value)){e.value=e.value.replace(/[a-f0-9]{64}/g,'[synthetic-token-redacted]');count++;}return count;});
   await page.screenshot({path:path.join(directory,'evidence/screenshots',item.name),fullPage:true});
   records.push({role:'guest',state:item.state,width,height:844,textScale:1,kind:item.kind,engine:'isolated SQLite',browser:browser.version(),url:page.url().replace(/[a-f0-9]{64}/g,'[synthetic-token-redacted]'),redactedFields,screenshot:'screenshots/'+item.name});
  }
 }
 fs.writeFileSync(path.join(directory,'evidence/redacted-payment-captures.json'),JSON.stringify({outcome:'PASS; readonly synthetic bearer-link display redacted only for screenshots',records},null,2));
 console.log(JSON.stringify({outcome:'PASS',screenshots:records.length}));
}finally{await browser.close();}
