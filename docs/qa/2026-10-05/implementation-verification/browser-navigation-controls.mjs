import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory=path.dirname(fileURLToPath(import.meta.url));
const label=process.argv[2]||'before';
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const records=[];
try{
 for(const role of ['owner','assistant']){
  const page=await browser.newPage({viewport:{width:390,height:844}});await page.goto('http://127.0.0.1:8124/login',{waitUntil:'networkidle'});
  await page.locator('[name=email]').fill(role+'@implementation.test');await page.locator('[name=password]').fill('preview-test-only');
  await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);await page.waitForLoadState('networkidle');
  for(const width of [360,390,430,768]){
   await page.setViewportSize({width,height:844});await page.locator('[aria-controls="staff-navigation"]').click();await page.waitForTimeout(250);
   const controls=await page.locator('[data-sidebar] button, [data-sidebar] a').evaluateAll(elements=>elements.filter(e=>e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden').map(e=>{const box=e.getBoundingClientRect();return{name:e.getAttribute('aria-label')||e.innerText?.trim().replace(/\s+/g,' '),width:box.width,height:box.height};}));
   const filename=`navigation-controls-${label}-${role}-${width}.png`;
   await page.screenshot({path:path.join(directory,'evidence/screenshots',filename),fullPage:true});
   const trigger=page.locator('[aria-controls="staff-navigation"]');
   await page.keyboard.press('Escape');
   const restored=await trigger.evaluate(e=>e===document.activeElement);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
   records.push({role,width,height:844,textScale:1,state:'open-navigation',engine:'isolated SQLite',browser:browser.version(),url:page.url(),controls,
       small:controls.filter(e=>e.width<43.9||e.height<43.9),escapeFocusRestored:restored,pageOverflow:overflow,screenshot:'screenshots/'+filename});
   if(label==='after'&&(controls.some(e=>e.width<43.9||e.height<43.9)||!restored||overflow>1))throw new Error('Navigation acceptance failed');
  }await page.close();
 }
 fs.writeFileSync(path.join(directory,'evidence/navigation-controls-'+label+'.json'),JSON.stringify(records,null,2));
 console.log(JSON.stringify(records.map(r=>({role:r.role,width:r.width,small:r.small}))));
}finally{await browser.close();}
