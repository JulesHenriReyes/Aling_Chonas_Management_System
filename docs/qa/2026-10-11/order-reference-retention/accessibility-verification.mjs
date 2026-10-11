import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(dir, 'preview-fixture.json')));
const base = 'http://127.0.0.1:8151';
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const page = await browser.newPage({viewport:{width:390,height:844}});
const measurements = [];
const checks = [];
function check(ok, description) { if (!ok) throw new Error(description); checks.push(description); }
async function ready() { await page.waitForFunction(() => !!window.Alpine && typeof tailwind !== 'undefined'); await page.evaluate(() => document.fonts.ready); }
try {
    await page.goto(base+'/login'); await ready();
    await page.locator('[name=email]').fill('owner@example.test');
    await page.locator('[name=password]').fill('password123');
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')),page.getByRole('button',{name:'Sign In to Staff System',exact:true}).click()]);
    for (const [label,url] of [['customer',base+'/order/payment/'+fixture.orders.photos.token],['staff',base+'/orders/'+fixture.orders.photos.id]]) {
        await page.goto(url); await ready();
        const notice = page.locator('p.design-muted').filter({hasText:'Reference photos expired after the 60-day'}).first();
        const measurement = await notice.evaluate(element => {
            const rgb = value => value.match(/[\d.]+/g).slice(0,3).map(Number);
            const luminance = color => rgb(color).map(value => { const channel=value/255; return channel<=0.04045?channel/12.92:((channel+0.055)/1.055)**2.4; }).reduce((sum,value,index)=>sum+value*[0.2126,0.7152,0.0722][index],0);
            const style = getComputedStyle(element);
            let background = 'rgb(255,255,255)';
            for (let parent=element;parent;parent=parent.parentElement) {
                const color=getComputedStyle(parent).backgroundColor;
                if (color!=='transparent' && color!=='rgba(0, 0, 0, 0)') {background=color;break;}
            }
            const values=[luminance(style.color),luminance(background)].sort((a,b)=>b-a);
            return {color:style.color,background,fontSize:parseFloat(style.fontSize),contrast:(values[0]+0.05)/(values[1]+0.05)};
        });
        measurements.push({label,...measurement});
        check(measurement.contrast>=4.5,label+': expiry text meets WCAG AA normal-text contrast (4.5:1)');
        const summary = page.locator('.design-history > summary');
        let reached=false;
        for (let index=0;index<30;index++) {
            await page.keyboard.press('Tab');
            if (await summary.evaluate(element=>element===document.activeElement)) {reached=true;break;}
        }
        check(reached,label+': design history is reachable by Tab');
        check(await summary.evaluate(element=>getComputedStyle(element).outlineStyle!=='none' && parseFloat(getComputedStyle(element).outlineWidth)>=2),label+': history has a visible keyboard focus outline');
        await page.keyboard.press('Enter');
        check(await summary.evaluate(element=>element.parentElement.open),label+': Enter opens the history disclosure');
        await page.evaluate(()=>document.documentElement.style.fontSize='200%');
        const resized = await notice.evaluate(element=>({fontSize:parseFloat(getComputedStyle(element).fontSize),width:element.getBoundingClientRect().width,scrollWidth:element.scrollWidth}));
        check(resized.fontSize>=measurement.fontSize*1.99,label+': notice text doubles at 200% root text size');
        check(resized.scrollWidth<=resized.width+1,label+': enlarged notice wraps within its mobile container');
        measurements.at(-1).resizedFontSize=resized.fontSize;
    }
    fs.writeFileSync(path.join(dir,'accessibility-results.json'),JSON.stringify({target:'WCAG 2.2 AA; changed notices and existing history disclosure only',viewport:{width:390,height:844},measurements,checks},null,2));
    console.log(JSON.stringify({checks:checks.length,measurements}));
} finally {await browser.close();}
