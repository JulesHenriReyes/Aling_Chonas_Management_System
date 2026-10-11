import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const dir=path.dirname(fileURLToPath(import.meta.url));
const fixture=JSON.parse(fs.readFileSync(path.join(dir,'preview-fixture.json')));
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const context=await browser.newContext({viewport:{width:1440,height:900}});
const customer=await context.newPage();
const staff=await browser.newPage({viewport:{width:390,height:844}});
const results=[];
const checks=(ok,text)=>{if(!ok)throw new Error(text);results.push(text);};
const base='http://127.0.0.1:8148';const url=base+'/order/payment/'+fixture.orders.pending.token;
function contrast(a,b){
    const channels=rgb=>rgb.match(/[\d.]+/g).slice(0,3).map(value=>{const c=Number(value)/255;return c<=.04045?c/12.92:((c+.055)/1.055)**2.4;});
    const lum=rgb=>{const c=channels(rgb);return .2126*c[0]+.7152*c[1]+.0722*c[2];};
    const x=lum(a),y=lum(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);
}
async function shot(page,name){await page.evaluate(()=>{document.activeElement?.blur();scrollTo({top:0,behavior:'instant'});});await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});}
try{
    await customer.goto(url+'/design-changes/new');await customer.waitForFunction(()=>!!window.Alpine && typeof tailwind!=='undefined');
    const theme=customer.locator('[name$="[themes]"]');
    await theme.focus();
    const focus=await theme.evaluate(el=>({style:getComputedStyle(el).outlineStyle,width:parseFloat(getComputedStyle(el).outlineWidth)}));
    checks(focus.style!=='none' && focus.width>=2,'Theme input has a visible 2px focus outline');
    const pairs=await customer.evaluate(()=>['.design-editor-fields label','.design-editor-fields .design-muted','.design-submit .ui-button.primary'].map(selector=>{
        const el=document.querySelector(selector),s=getComputedStyle(el);let node=el;let background;
        while(node){background=getComputedStyle(node).backgroundColor;if(background!=='rgba(0, 0, 0, 0)')break;node=node.parentElement;}
        return {selector,foreground:s.color,background};
    }));
    for(const pair of pairs){pair.ratio=Number(contrast(pair.foreground,pair.background).toFixed(2));checks(pair.ratio>=4.5,`${pair.selector} contrast ${pair.ratio}:1 meets the 4.5:1 text target`);}
    await theme.fill('SageGreenCreamFlowers'.repeat(12).slice(0,255));
    await customer.locator('[name$="[special_request]"]').fill('Small cream flowers around the base. '.repeat(23));
    await customer.locator('[name=pause_acknowledged]').check();
    await Promise.all([customer.waitForURL(url+'#design-change'),customer.getByRole('button',{name:'Submit change request',exact:true}).click()]);
    await customer.waitForFunction(()=>!!window.Alpine && typeof tailwind!=='undefined');
    await customer.setViewportSize({width:390,height:844});
    await customer.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);
    checks(true,'Maximum-length unbroken proposed theme and long instructions fit the phone comparison');
    await shot(customer,'long-content-mobile');
    await staff.goto(base+'/login');await staff.locator('[name=email]').fill('owner@example.test');await staff.locator('[name=password]').fill('password123');
    await Promise.all([staff.waitForURL('**/dashboard'),staff.locator('button[type=submit]').click()]);
    await staff.goto(base+'/orders/'+fixture.orders.pending.id);await staff.waitForFunction(()=>!!window.Alpine && typeof tailwind!=='undefined');
    await staff.locator('[data-design-decline] summary').focus();await staff.keyboard.press('Enter');
    checks(await staff.locator('[data-design-decline]').getAttribute('open')!==null,'Keyboard opens the staff decline form');
    await staff.locator('form[action$="/approve"] [name=feasibility_confirmed]').check();
    await Promise.all([staff.waitForNavigation(),staff.getByRole('button',{name:'Approve design change',exact:true}).click()]);
    await customer.reload();await customer.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);
    checks(true,'Approved maximum-length theme fits the saved customer design on a phone');
    await staff.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);
    checks(true,'Approved maximum-length theme fits the saved staff design on a phone');
    await shot(customer,'long-approved-mobile');
    const zoom=await browser.newPage({viewport:{width:720,height:450},deviceScaleFactor:2});
    await zoom.goto(url+'/design-changes/new');await zoom.waitForFunction(()=>!!window.Alpine && typeof tailwind!=='undefined');
    await zoom.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);
    const button=await zoom.getByRole('button',{name:'Submit change request',exact:true}).boundingBox();
    checks(button.x>=0 && button.x+button.width<=720,'200% desktop zoom geometry (720 CSS px / 1440 physical px) retains the submission control without horizontal overflow');
    await shot(zoom,'editor-200-percent');
    fs.writeFileSync(path.join(dir,'accessibility-results.json'),JSON.stringify({target:'Targeted WCAG AA checks; no claim of a full accessibility audit.',checks:results,contrast:pairs},null,2));
    console.log(`${results.length} accessibility and long-content checks passed`);
}finally{await browser.close();}
