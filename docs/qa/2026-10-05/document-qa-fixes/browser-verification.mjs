import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(directory, 'preview-fixture.json')));
const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const evidence = {captures:[], checks:[], failures:[]};
const contexts = [];
fs.mkdirSync(path.join(directory, 'screenshots'), {recursive:true});
const check = (name, ok) => { assert.ok(ok, name); evidence.checks.push(name); };
async function go(page, url) {
    const response = await page.goto(fixture.url+url, {waitUntil:'load'});
    assert.equal(response.status(), 200);
    await page.waitForFunction(() => window.tailwind && (!document.querySelector('[x-data]') || window.Alpine));
    await page.evaluate(() => document.fonts.ready);
}
async function submit(page, button) {
    await Promise.all([page.waitForNavigation({waitUntil:'load'}), button.click()]);
}
async function capture(page, name) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth-innerWidth);
    const geometry = await page.evaluate(() => ({viewport:innerWidth,root:document.documentElement.scrollWidth,body:document.body.scrollWidth,
        elements:[...document.querySelectorAll('body *')].filter(el=>el.getClientRects().length)
            .map(el=>({tag:el.tagName,class:typeof el.className==='string'?el.className:'svg',left:el.getBoundingClientRect().left,right:el.getBoundingClientRect().right,scroll:el.scrollWidth,client:el.clientWidth,overflow:getComputedStyle(el).overflowX}))
            .filter(box=>box.right>innerWidth+1 || (box.scroll>box.client+1 && box.overflow==='visible')).slice(0,35)}));
    const file = 'screenshots/'+name+'.png';
    await page.screenshot({path:path.join(directory,file),fullPage:true});
    const overflowElements = await page.evaluate(() => [...document.querySelectorAll('body,body > *,main,main > *,main .workspace,main .workspace > *')]
        .map(element => ({tag:element.tagName,class:element.className,right:element.getBoundingClientRect().right,width:element.getBoundingClientRect().width}))
        .filter(box => box.right>innerWidth+1));
    evidence.captures.push({name,file,width:page.viewportSize().width,overflow,overflowElements,geometry});
    check(name+' has no page overflow', overflow <= 1);
}
try {
    for (const role of ['owner','assistant']) {
        const context = await browser.newContext({viewport:{width:1440,height:960}});
        contexts.push(context);
        const page = await context.newPage();
        page.on('dialog', dialog => dialog.accept());
        await go(page,'/login');
        await page.locator('[name=email]').fill(role+'@document-qa.test');
        await page.locator('[name=password]').fill('preview-only-password');
        await submit(page,page.locator('button[type=submit]'));
        for (const width of [390,1440]) {
            await page.setViewportSize({width,height:960});
            await go(page,'/supplies');
            check(role+' sortable headings limited to Supply and Category',
                JSON.stringify(await page.locator('.sort-heading').allTextContents())===JSON.stringify(['Supply ↕','Category ↕']));
            check(role+' reorder column hidden', !(await page.locator('thead').innerText()).includes('Reorder at'));
            check(role+' Stock in and Stock out present', await page.getByRole('link',{name:'Stock in',exact:true}).count()===1 && await page.getByRole('link',{name:'Stock out',exact:true}).count()===1);
            check(role+' statuses still reflect threshold', await page.locator('tbody .status-attention').count()===1 && await page.locator('tbody .status-danger').count()===1);
            const menu = page.locator('.supply-actions').first();
            const trigger = menu.locator('summary');
            await trigger.scrollIntoViewIfNeeded();
            const box = await trigger.boundingBox();
            check(role+' menu hit target at least 44px',box.width>=43.5 && box.height>=43.5);
            check(role+' menu has accessible name', (await trigger.getAttribute('aria-label')).startsWith('Options for '));
            await trigger.focus();
            await trigger.press('Enter');
            await menu.locator('a').first().waitFor({state:'visible'});
            await trigger.press('ArrowDown');
            check(role+' keyboard menu focuses Edit', await menu.locator('a').first().evaluate(el=>el===document.activeElement));
            await capture(page,role+'-supplies-'+width);
            await page.keyboard.press('Escape');
            check(role+' Escape closes menu',await menu.getAttribute('open')===null);
            await go(page,'/inventory/create/usage');
            check(role+' Stock out form heading', await page.locator('h1').innerText()==='Stock out');
            await page.locator('#find-supply').fill('Flour');
            await page.locator('#supply-search-results button').first().click();
            const quantity = page.locator('#stock-quantity-'+fixture.supply_id);
            await quantity.fill('2');
            await page.locator('#operation-type').selectOption('stocktake');
            check(role+' changing to count clears reduction value', await quantity.inputValue()==='');
            check(role+' count semantics explained', await page.getByText('A stock count sets the quantity on hand. It can increase or decrease recorded stock.').isVisible());
            await quantity.fill('6');
            await capture(page,role+'-stock-count-'+width);
            await page.locator('#operation-type').selectOption('usage');
            check(role+' changing back clears count value', await quantity.inputValue()==='');
        }
        if (role==='owner') {
            // Exercise actual posting under the unified selector with existing audit rules.
            for (const [type, quantity, expected] of [['usage','2','8.00'],['waste','1','7.00'],['stocktake','6','6.00']]) {
                await go(page,'/inventory/create/usage');
                await page.locator('#operation-type').selectOption(type);
                await page.locator('#find-supply').fill('Flour');
                await page.locator('#supply-search-results button').first().click();
                await page.locator('#stock-quantity-'+fixture.supply_id).fill(quantity);
                if (type!=='usage') await page.locator('#notes').fill('Synthetic '+type+' verification');
                await submit(page,page.getByRole('button',{name:type==='stocktake'?'Save stock count':'Save stock out',exact:true}));
                check(type+' operation posted',/^\/inventory\/\d+$/.test(new URL(page.url()).pathname));
                await go(page,'/supplies');
                const row = page.locator('tbody tr').filter({has:page.getByRole('link',{name:'Flour',exact:true})});
                check(type+' stock balance correct',await row.locator('td').nth(3).innerText()===expected);
            }
            await go(page,'/inventory/create/receipt');
            await page.locator('#find-supply').fill('Flour');
            await page.locator('#supply-search-results button').first().click();
            await page.locator('#stock-quantity-'+fixture.supply_id).fill('4');
            await submit(page,page.getByRole('button',{name:'Save stock in',exact:true}));
            await go(page,'/supplies');
            const row = page.locator('tbody tr').filter({has:page.getByRole('link',{name:'Flour',exact:true})});
            check('Stock in balance correct',await row.locator('td').nth(3).innerText()==='10.00');
        }
    }
    const customerContext = await browser.newContext({viewport:{width:390,height:844}});
    contexts.push(customerContext);
    const buyer = await customerContext.newPage();
    await customerContext.route('**/order/payment/*/receipt',route => route.continue({headers:{...route.request().headers(),referer:fixture.url+'/dashboard'}}));
    await go(buyer,fixture.public_page);
    await buyer.locator('[name=reference_number]').fill('KEEP-PREVIEW-REFERENCE');
    await buyer.locator('[name=receipt]').setInputFiles({name:'bad-receipt.txt',mimeType:'text/plain',buffer:Buffer.from('not an image')});
    await submit(buyer,buyer.locator('form[action$="/receipt"] button[type=submit]'));
    check('invalid upload stays on customer order',new URL(buyer.url()).pathname===fixture.public_page);
    check('reference number retained',await buyer.locator('[name=reference_number]').inputValue()==='KEEP-PREVIEW-REFERENCE');
    check('customer sees upload error',await buyer.locator('[name=receipt]').getAttribute('aria-invalid')==='true');
    await buyer.evaluate(()=>document.querySelectorAll('input[readonly]').forEach(input=>input.value='[Private link redacted]'));
    await capture(buyer,'customer-invalid-upload-390');
    const guest = await customerContext.request.get(fixture.url+'/supplies',{maxRedirects:0});
    check('inventory remains restricted to staff',guest.status()===302 && guest.headers().location.endsWith('/login'));
    evidence.browser=browser.version();
} catch (error) {
    evidence.failures.push(String(error.message).replace(/[a-f0-9]{64}/g,'[private-token]'));
    process.exitCode=1;
} finally {
    fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(evidence,null,2));
    for (const context of contexts) await context.close();
    await browser.close();
}
console.log(JSON.stringify({checks:evidence.checks.length,captures:evidence.captures.length,failures:evidence.failures}));
