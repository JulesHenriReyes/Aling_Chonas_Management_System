import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const dir=path.dirname(fileURLToPath(import.meta.url));
const fixture=JSON.parse(fs.readFileSync(path.join(dir,'preview-fixture.json')));
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const page=await browser.newPage({viewport:{width:390,height:844}});
try {
    await page.goto('http://127.0.0.1:8148/login'); await page.locator('[name=email]').fill('assistant@example.test'); await page.locator('[name=password]').fill('password123');
    await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
    await page.goto('http://127.0.0.1:8148/orders/'+fixture.orders.deposit.id);
    await page.waitForFunction(()=>!!window.Alpine && typeof tailwind!=='undefined');
    await page.evaluate(()=>document.fonts.ready);
    console.log(JSON.stringify(await page.evaluate(()=>{
        let node=document.querySelector('.order-payments table');const chain=[];
        while(node){const r=node.getBoundingClientRect(),s=getComputedStyle(node);chain.push({tag:node.tagName,class:node.className,overflow:s.overflow,width:r.width,scroll:node.scrollWidth,client:node.clientWidth,contain:s.contain,display:s.display});node=node.parentElement;}
        return {viewport:innerWidth,document:document.documentElement.scrollWidth,chain};
    }),null,2));
    console.log(JSON.stringify(await page.evaluate(()=>Array.from(document.querySelectorAll('main *')).map(el=>{
        const rect=el.getBoundingClientRect();const style=getComputedStyle(el);
        return {tag:el.tagName,class:el.className,text:el.textContent.trim().slice(0,70),left:rect.left,right:rect.right,width:rect.width,display:style.display,minWidth:style.minWidth};
    }).filter(el=>el.width>0 && el.right>innerWidth+1).slice(0,22)),null,2));
    await page.screenshot({path:path.join(dir,'staff-mobile-initial.png'),fullPage:true});
} finally {await browser.close();}
