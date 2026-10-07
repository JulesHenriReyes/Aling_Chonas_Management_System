import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const evidence = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(evidence, '../staff-order-modernization/preview-fixture.json')));
const disposableRoot = path.resolve(fixture.root).toLowerCase();
if (!disposableRoot.includes('appdata\\local\\temp') || !path.basename(fixture.database).startsWith('workflow-concurrency-')) throw new Error('Expected the existing disposable preview fixture.');
const baseline = process.argv.includes('--baseline');
const base = 'http://127.0.0.1:8138';
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const context = await browser.newContext({viewport:{width:1440,height:1000}});
const page = await context.newPage();
const results = {checks:[], layouts:[], screenshots:[], javascriptErrors:[], failedResponses:[]};
page.on('pageerror', error => {if (!error.message.includes('ViewTransition opt-in disabled')) results.javascriptErrors.push(error.message);});
page.on('response', response => {if (response.url().startsWith(base) && response.status() >= 500) results.failedResponses.push({url:response.url(),status:response.status()});});
function check(condition, message) {if (!condition) throw new Error(message); results.checks.push(message);}
async function editor() {
    await page.locator('.store-package a').first().click();
    await page.locator('#theme-1').waitFor();
    await page.evaluate(() => document.fonts.ready);
    await page.locator('#theme-1').fill('Blue and cream with buttercream flowers');
    await page.locator('#request-1').fill('Happy birthday, Maria Alexandra De la Cruz Santiago. Keep the inscription legible.');
    await page.waitForTimeout(650);
    await page.waitForFunction(() => !Alpine.$data(document.querySelector('[data-package-editor]')).uploading);
}
async function layout(flow, width) {
    await page.setViewportSize({width,height:1000});
    await page.evaluate(() => {document.activeElement?.blur(); window.scrollTo({top:0,behavior:'instant'});});
    await page.waitForTimeout(300);
    const measured = await page.evaluate(() => {
        const rect = el => {const {x,y,width,height,bottom,right} = el.getBoundingClientRect(); return {x,y,width,height,bottom,right};};
        const theme = document.querySelector('#theme-1'), request = document.querySelector('#request-1');
        return {
            width:innerWidth, overflow:document.documentElement.scrollWidth-innerWidth,
            theme:rect(theme), request:rect(request),
            themeLabel:rect(document.querySelector('label[for="theme-1"]')),
            requestLabel:rect(document.querySelector('label[for="request-1"]')),
            photos:rect(document.querySelector('#images-1')),
            actions:[...document.querySelectorAll('[data-package-editor] button[type=submit], [data-package-editor] a.ui-button')].map(el => rect(el)),
        };
    });
    results.layouts.push({flow,...measured});
    if (baseline && flow === 'staff' && width === 1440) check(measured.request.y > measured.theme.y + 10, 'Reproduced wrapped-label misalignment on staff at 1440px');
    if (!baseline) {
        check(measured.overflow <= 1, `${flow}: no horizontal overflow at ${width}px`);
        check(measured.photos.y >= Math.max(measured.theme.bottom,measured.request.bottom), `${flow}: photo upload stays below design fields at ${width}px`);
        check(measured.actions.every(rect => rect.height >= 44 && rect.x >= 0 && rect.right <= width+1), `${flow}: actions remain accessible at ${width}px`);
        if (flow === 'staff') {
            if (Math.abs(measured.request.x-measured.theme.x) > 1) check(Math.abs(measured.request.y-measured.theme.y) <= 1, `Staff: side-by-side controls align at ${width}px`);
            else check(measured.requestLabel.y >= measured.theme.bottom, `Staff: fields stack without overlap at ${width}px`);
        }
    }
    if ([390,768,1440].includes(width)) {
        const name = `${baseline ? 'before' : 'after'}-${flow}-${width}.png`;
        await page.screenshot({path:path.join(evidence,name),fullPage:true});
        results.screenshots.push(name);
    }
}
try {
    await page.goto(`${base}/login`);
    await page.locator('[name=email]').fill('owner@example.test');
    await page.locator('[name=password]').fill('password123');
    await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
    await page.goto(`${base}/orders/create`); await editor();
    for (const width of [390,768,1024,1280,1440]) await layout('staff',width);
    if (!baseline) {
        await page.locator('#theme-1').focus(); await page.keyboard.press('Tab');
        check(await page.locator('#request-1').evaluate(el => el === document.activeElement), 'Staff: Tab moves from theme to instructions');
        await page.keyboard.press('Tab');
        check(await page.locator('#images-1').evaluate(el => el === document.activeElement), 'Staff: Tab moves from instructions to photo upload');
        await page.reload(); await page.locator('#theme-1').waitFor();
        check(await page.locator('#theme-1').inputValue() === 'Blue and cream with buttercream flowers', 'Staff: theme survives reload');
        check((await page.locator('#request-1').inputValue()).startsWith('Happy birthday, Maria Alexandra'), 'Staff: instructions survive reload');
        // Render the existing server validation path with over-limit values, using only synthetic draft data.
        await page.locator('#theme-1').evaluate(el => el.removeAttribute('maxlength'));
        await page.locator('#request-1').evaluate(el => el.removeAttribute('maxlength'));
        await page.locator('#theme-1').fill('T'.repeat(256));
        await page.locator('#request-1').fill('R'.repeat(1001));
        await page.waitForTimeout(650);
        await page.waitForFunction(() => !Alpine.$data(document.querySelector('[data-package-editor]')).uploading);
        await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Save package to order',exact:true}).click()]);
        await page.locator('#theme-1[aria-invalid=true]').waitFor();
        const errors = await page.evaluate(() => ['theme-1','request-1'].map(id => {
            const field = document.getElementById(id), error = document.getElementById(field.getAttribute('aria-describedby'));
            return {id,error:!!error,text:error?.textContent,errorTop:error?.getBoundingClientRect().top,fieldBottom:field.getBoundingClientRect().bottom};
        }));
        check(errors.every(error => error.error && error.text && error.errorTop >= error.fieldBottom), 'Staff: validation errors remain beneath their controls');
        await layout('staff-validation',1440);
        const errorLayout=results.layouts.at(-1);
        check(Math.abs(errorLayout.theme.y-errorLayout.request.y) <= 1, 'Staff: alignment survives both field validation errors');
        await page.screenshot({path:path.join(evidence,'after-staff-validation-1440.png'),fullPage:true});
        results.screenshots.push('after-staff-validation-1440.png');
    }
    await page.goto(base); await editor();
    for (const width of [390,768,1024,1280,1440]) await layout('public',width);
    if (!baseline) {
        const before=JSON.parse(fs.readFileSync(path.join(evidence,'baseline.json')));
        for (const after of results.layouts.filter(item => item.flow === 'public')) {
            const original=before.layouts.find(item => item.flow === 'public' && item.width === after.width);
            check(['theme','request','themeLabel','requestLabel','photos'].every(key => ['x','y','width','height'].every(dimension => Math.abs(original[key][dimension]-after[key][dimension]) <= 1)), `Public: geometry unchanged at ${after.width}px`);
        }
        check(results.javascriptErrors.length === 0 && results.failedResponses.length === 0, 'No application JavaScript errors or server failures');
    }
} finally {
    fs.writeFileSync(path.join(evidence,baseline ? 'baseline.json' : 'results.json'),JSON.stringify(results,null,2)+'\n');
    await browser.close();
}
console.log(`${results.checks.length} checks passed; ${results.screenshots.length} screenshots saved.`);
