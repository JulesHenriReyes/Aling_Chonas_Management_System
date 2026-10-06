import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(directory, 'preview-fixture.json')));
const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const evidence = {checks:[], captures:[], consoleErrors:[], browserNotices:[], failures:[]};
const contexts = [];
fs.mkdirSync(path.join(directory,'screenshots'),{recursive:true});
const check = (name, result) => { assert.ok(result,name); evidence.checks.push(name); };
async function go(page, url) {
    const response = await page.goto(fixture.url+url,{waitUntil:'load'});
    assert.equal(response.status(),200);
    await page.waitForFunction(() => window.tailwind && (!document.querySelector('[x-data]') || window.Alpine));
    await page.evaluate(() => document.fonts.ready);
}
async function submit(page, button) {
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),button.click()]);
}
async function capture(page, name) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth-innerWidth);
    const geometry = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el=>el.getClientRects().length)
        .map(el=>({tag:el.tagName,class:typeof el.className==='string'?el.className:'svg',left:el.getBoundingClientRect().left,right:el.getBoundingClientRect().right,width:el.clientWidth,scroll:el.scrollWidth,overflow:getComputedStyle(el).overflowX,position:getComputedStyle(el).position}))
        .filter(box=>box.right>innerWidth+1 || (box.scroll>box.width+1 && box.overflow==='visible')).slice(0,35));
    const file = 'screenshots/'+name+'.png';
    await page.screenshot({path:path.join(directory,file),fullPage:true});
    evidence.captures.push({name,file,width:page.viewportSize().width,overflow,geometry});
    check(name+' has no page overflow',overflow<=1);
}
const pick = (page,name) => page.locator('#supply-search-results button').filter({has:page.locator('.supply-result-name',{hasText:new RegExp('^'+name+'$')})});
try {
    for (const role of ['owner','assistant']) {
        const context = await browser.newContext({viewport:{width:1440,height:960}});
        contexts.push(context);
        const page = await context.newPage();
        page.on('dialog',dialog=>dialog.accept());
        page.on('pageerror',error=> {
            const target = error.message === 'Transition was aborted because of invalid state. ViewTransition opt-in disabled' ? evidence.browserNotices : evidence.consoleErrors;
            target.push(error.message);
        });
        await go(page,'/login');
        await page.locator('[name=email]').fill(role+'@inventory-qa.test');
        await page.locator('[name=password]').fill('preview-only-password');
        await submit(page,page.locator('button[type=submit]'));
        for (const width of [390,1440]) {
            await page.setViewportSize({width,height:960});
            await go(page,'/supplies');
            check(role+' '+width+' combined on-hand column',await page.locator('thead th').count()===5 && !(await page.locator('thead').innerText()).includes('Unit'));
            check(role+' '+width+' quantities include units',(await page.locator('tbody').innerText()).includes('24.00 piece'));
            const tableWidths = await page.locator('thead th').evaluateAll(cells=>cells.map(cell=>cell.getBoundingClientRect().width));
            check(role+' '+width+' action column compact',tableWidths.at(-1)<=74 && tableWidths[0]>tableWidths.at(-1));
            await capture(page,role+'-inventory-'+width);

            await go(page,'/inventory/create/receipt');
            check(role+' '+width+' delivery wording removed',!(await page.locator('main').innerText()).toLowerCase().includes('delivery'));
            const toggle = page.getByRole('button',{name:'Toggle supply dropdown'});
            check(role+' '+width+' dropdown initially closed',await toggle.getAttribute('aria-expanded')==='false');
            await toggle.click();
            await pick(page,'Flour').waitFor({state:'visible'});
            check(role+' '+width+' browse excludes inactive supplies',!(await page.locator('#supply-search-results').innerText()).includes('Inactive flour'));
            await pick(page,'Flour').click();
            check(role+' '+width+' picker stays open after selection',await page.locator('#supply-search-results').isVisible());
            check(role+' '+width+' selected supply is marked Added',await pick(page,'Flour').isDisabled() && (await pick(page,'Flour').innerText()).includes('Added'));
            await pick(page,'Sugar').click();
            check(role+' '+width+' multiple supplies selected without reopening',await page.locator('.batch-table input[type=number]').count()===2);
            check(role+' '+width+' selection preserves search',await page.locator('#find-supply').inputValue()==='');
            const flourQuantity = page.locator('#stock-quantity-'+fixture.supply_ids.Flour);
            await flourQuantity.fill('2');
            await page.locator('#stock-quantity-'+fixture.supply_ids.Sugar).fill('2');
            check(role+' '+width+' preview quantities include units',(await page.locator('.batch-table tbody').innerText()).includes('12.00 kg'));
            await capture(page,role+'-picker-'+width);
            await page.locator('h1').click();
            check(role+' '+width+' outside click closes picker',await toggle.getAttribute('aria-expanded')==='false');
            await page.locator('#find-supply').focus();
            await page.locator('#find-supply').press('ArrowDown');
            await page.waitForFunction(()=>document.activeElement?.matches('#supply-search-results button'));
            await page.keyboard.press('Enter');
            check(role+' '+width+' keyboard adds another supply',await page.locator('.batch-table input[type=number]').count()===3);
            await page.keyboard.press('Escape');
            check(role+' '+width+' Escape closes picker and restores search focus',await toggle.getAttribute('aria-expanded')==='false' && await page.locator('#find-supply').evaluate(el=>el===document.activeElement));
            await page.getByRole('button',{name:'Remove Baking powder',exact:true}).click();
            await toggle.click();
            await page.locator('#find-supply').fill('Cocoa');
            await page.waitForFunction(()=>document.querySelectorAll('#supply-search-results button').length===1 && document.querySelector('.supply-result-name')?.textContent==='Cocoa');
            await pick(page,'Cocoa').click();
            check(role+' '+width+' typed selection retains its search and list',await page.locator('#find-supply').inputValue()==='Cocoa' && await page.locator('#supply-search-results').isVisible());
            await toggle.click();
            check(role+' '+width+' dropdown button closes list',await toggle.getAttribute('aria-expanded')==='false');

            await go(page,'/inventory/create/usage');
            await page.locator('#find-supply').fill('Flour');
            await pick(page,'Flour').click();
            const quantity = page.locator('#stock-quantity-'+fixture.supply_ids.Flour);
            await quantity.fill('2');
            await page.locator('#operation-type').selectOption('stocktake');
            check(role+' '+width+' count mode still clears reduction quantity',await quantity.inputValue()==='');
            await quantity.fill('6');
            check(role+' '+width+' count preview sets remaining quantity',(await page.locator('.batch-table tbody').innerText()).includes('6.00 kg'));
            check(role+' '+width+' quantity unit stays on one line',await page.locator('.stock-quantity-input > span').first().evaluate(el=>el.getBoundingClientRect().height<=parseFloat(getComputedStyle(el).lineHeight)+1));
            await capture(page,role+'-stock-count-'+width);
        }
        await page.setViewportSize({width:390,height:960});
        await go(page,'/inventory/create/receipt');
        await page.locator('#find-supply').fill('missing ingredient');
        await page.getByRole('button',{name:'Add a new supply',exact:true}).click();
        const dialog = page.getByRole('dialog');
        await dialog.waitFor({state:'visible'});
        check(role+' new supply starts with typed name',await dialog.locator('[name=supply_name]').inputValue()==='missing ingredient');
        check(role+' no separate ingredient-template dropdown',await dialog.locator('#new-preset').count()===0);
        const name = role==='owner' ? 'Evaporated milk' : 'Baking soda';
        const unit = role==='owner' ? 'can' : 'g';
        await dialog.locator('[name=supply_name]').fill(name);
        await dialog.locator('[name=unit]').fill(unit);
        check(role+' supply name and unit entered directly',await dialog.locator('[name=supply_name]').inputValue()===name && await dialog.locator('[name=unit]').inputValue()===unit);
        await capture(page,role+'-new-supply-mobile');
        await Promise.all([page.waitForResponse(response=>response.url()===fixture.url+'/supplies' && response.request().method()==='POST'),dialog.getByRole('button',{name:'Create & add to stock in'}).click()]);
        await dialog.waitFor({state:'hidden'});
        check(role+' new supply added to batch at zero',(await page.locator('.batch-table tbody').innerText()).includes('0.00 '+unit));
        const newQuantity = page.locator('.batch-table input[type=number]');
        await newQuantity.fill(role==='owner' ? '4' : '250');
        if (role==='owner') {
            await pick(page,'Flour').click();
            await page.locator('#stock-quantity-'+fixture.supply_ids.Flour).fill('2');
        }
        await capture(page,role+'-new-stock-in-mobile');
        await submit(page,page.getByRole('button',{name:'Save stock in',exact:true}));
        check(role+' stock in saved as one grouped operation',/\/inventory\/\d+$/.test(new URL(page.url()).pathname));
        check(role+' operation has no delivery reference',!(await page.locator('main').innerText()).toLowerCase().includes('delivery'));
        await go(page,'/supplies');
        check(role+' saved supply appears in database-backed picker', (await (await page.request.get(fixture.url+'/supplies/lookup?q='+encodeURIComponent(name))).json()).some(supply=>supply.supply_name===name && supply.unit===unit));
        check(role+' new stock amount saved with unit',(await page.locator('tbody').innerText()).includes((role==='owner' ? '4.00' : '250.00')+' '+unit));
        if (role==='owner') {
            check('existing flour stock increases exactly once',(await page.locator('tbody').innerText()).includes('12.00 kg'));
            // Duplicate creation errors stay in the dialog and do not discard the batch.
            await go(page,'/inventory/create/receipt');
            await page.getByRole('button',{name:'Add a new supply',exact:true}).click();
            await dialog.locator('[name=supply_name]').fill('Evaporated milk');
            await dialog.locator('[name=unit]').fill('can');
            await dialog.getByRole('button',{name:'Create & add to stock in'}).click();
            await dialog.locator('[role=alert]').waitFor({state:'visible'});
            check('duplicate ingredient shows validation error in place',(await dialog.locator('[role=alert]').innerText()).includes('already been taken'));
            await dialog.getByRole('button',{name:'Cancel',exact:true}).click();
        } else {
            await go(page,'/supplies/create');
            check('standalone form has no ingredient-template dropdown',await page.locator('#supply-preset').count()===0);
            await page.locator('[name=unit]').fill('kg');
            await page.locator('[name=supply_name]').fill('Vegetable oil reserve');
            await capture(page,'assistant-add-supply-mobile');
            await submit(page,page.getByRole('button',{name:'Save & stock in',exact:true}));
            check('standalone creation goes directly to stock in',new URL(page.url()).pathname==='/inventory/create/receipt' && new URL(page.url()).searchParams.has('supply_id'));
            check('standalone creation preselects a zero-stock row',await page.getByText('Vegetable oil reserve',{exact:true}).isVisible() && (await page.locator('.batch-table tbody td').nth(1).innerText())==='0.00 kg');
        }
    }
    check('no Alpine or browser errors',evidence.consoleErrors.length===0);
} catch(error) {
    evidence.failures.push({message:error.message,stack:error.stack});
    console.error(error.stack);
    process.exitCode=1;
} finally {
    fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(evidence,null,2));
    for(const context of contexts) await context.close();
    await browser.close();
    console.log(JSON.stringify({checks:evidence.checks.length,captures:evidence.captures.length,failures:evidence.failures.length,consoleErrors:evidence.consoleErrors.length}));
}
