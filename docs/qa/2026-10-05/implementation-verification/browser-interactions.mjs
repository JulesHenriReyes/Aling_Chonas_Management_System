import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {chromium} from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const directory = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(directory,'evidence/preview-fixture.json')));
const browser = await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const results=[];
const snapshot = () => JSON.parse(execFileSync('C:/xampp/php/php.exe',[path.join(directory,'fixture-snapshot.php')],{encoding:'utf8'}));
async function settle(page) { await page.waitForFunction(()=>window.tailwind&&(!document.querySelector('[x-data]')||window.Alpine)); await page.evaluate(()=>document.fonts.ready); await page.waitForTimeout(100); }
async function go(page,url) { const response=await page.goto(fixture.url+url,{waitUntil:'load'}); assert.equal(response.status(),200); await settle(page); }
async function shot(page,state) {
    for(const width of [360,390,430,768,1024,1440]) {
        await page.setViewportSize({width,height:844});
        const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
        assert(overflow<=1,`${state} at ${width} overflows ${overflow}px`);
        const name=`interaction-${state}-${width}.png`;
        await page.screenshot({path:path.join(directory,'evidence/screenshots',name),fullPage:true});
        results.push({state,width,height:844,textScale:1,role:state.startsWith('public-')?'guest':state==='inline-expired-session'?'owner interface with expired authentication':'owner',
            url:page.url().replace(/[a-f0-9]{64}/g,'[synthetic-token-redacted]').replace(/[a-f0-9-]{36}/g,'[draft-key]'),
            engine:'isolated SQLite',database:path.basename(fixture.database),browser:browser.version(),overflow,screenshot:'screenshots/'+name});
        fs.writeFileSync(path.join(directory,'evidence/browser-interactions-progress.json'),JSON.stringify({results},null,2));
    }
    await page.setViewportSize({width:390,height:844});
}
async function postForm(page,button) {
    await page.locator('form').filter({has:button}).evaluate(form=>form.noValidate=true);
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),button.click()]); await settle(page);
}
try {
    const context=await browser.newContext({viewport:{width:390,height:844}});
    const page=await context.newPage();
    page.on('dialog',dialog=>dialog.accept());
    await go(page,'/login'); await page.locator('[name=email]').fill('owner@implementation.test'); await page.locator('[name=password]').fill('preview-test-only');
    await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]); await settle(page);

    await go(page,'/orders/'+fixture.orders.ready);
    const beforeFinancial=snapshot();
    await postForm(page,page.getByRole('button',{name:'Complete pickup',exact:true}));
    assert.equal(await page.locator('[name=pickup_confirmed]').getAttribute('aria-invalid'),'true');
    assert.equal(await page.locator('[data-error-for=pickup_confirmed]').count(),1);
    assert.deepEqual(snapshot(),beforeFinancial,'Unacknowledged pickup must not mutate anything');
    await shot(page,'pickup-ack-error');
    await page.locator('summary.bakery-failure-action').click();
    await page.locator('[name=reason]').fill('A specific actual bakery failure, preserved on error.');
    await postForm(page,page.getByRole('button',{name:'Mark bakery failure',exact:true}));
    assert.equal(await page.locator('[name=reason]').inputValue(),'A specific actual bakery failure, preserved on error.');
    assert(await page.locator('details:has(.bakery-failure-action)').evaluate(e=>e.open),'Error automatically opens failure disclosure');
    assert.equal(await page.locator('[name=bakery_failure_confirmed]').getAttribute('aria-invalid'),'true');
    assert.deepEqual(snapshot(),beforeFinancial,'Unacknowledged failure must not mutate anything');
    await shot(page,'failure-ack-error');
    await go(page,'/orders/'+fixture.orders['failure-pending']);
    await page.locator('[name=method]').selectOption('cash'); await page.locator('#refund-reference').fill('PRESERVE-CASH-RETURN');
    await postForm(page,page.getByRole('button',{name:'Confirm completed refund'}));
    assert.equal(await page.locator('#refund-reference').inputValue(),'PRESERVE-CASH-RETURN');
    assert.equal(await page.locator('[name=method]').inputValue(),'cash');
    assert.deepEqual(snapshot(),beforeFinancial,'Unacknowledged refund must not mutate anything');
    await shot(page,'refund-ack-error');

    await go(page,'/inventory/create/stocktake'); await page.locator('#find-supply').fill('Flour');
    await page.getByRole('button',{name:'Search supplies',exact:true}).click();
    await page.locator('.supply-results button').first().click();
    await page.locator('[name="lines[0][quantity]"]').fill('8');
    const beforeStock=snapshot();
    await postForm(page,page.getByRole('button',{name:'Post stock operation'}));
    assert.equal(await page.locator('[name="lines[0][quantity]"]').inputValue(),'8');
    assert.equal(await page.locator('#notes').getAttribute('aria-invalid'),'true');
    assert.deepEqual(snapshot(),beforeStock,'Missing stocktake reason must not write');
    await shot(page,'stocktake-reason-error');
    const token=await page.locator('form[data-safe-form] [name=_token]').inputValue();
    const receipt=await page.request.post(fixture.url+'/inventory',{form:{_token:token,submission_key:crypto.randomUUID(),type:'receipt',operation_date:'2026-10-05',notes:'Browser fixture concurrent delivery','lines[0][supply_id]':fixture.supply,'lines[0][quantity]':'3','lines[0][expected_version]':'0'}});
    assert.equal(receipt.status(),200);
    await page.locator('#notes').fill('Actual physical recount for browser acceptance');
    const beforeStale=snapshot();
    await postForm(page,page.getByRole('button',{name:'Post stock operation'}));
    assert(await page.getByText('Stock changed while this count was open.',{exact:false}).first().isVisible());
    assert.deepEqual(snapshot(),beforeStale,'Stale stocktake must not write');
    await shot(page,'stocktake-stale-error');
    await page.getByRole('button',{name:'Reload current stock for Flour'}).click();
    await page.waitForFunction(version=>document.querySelector('[name="lines[0][expected_version]"]').value===String(version),Number(beforeStock['supply-1'].stock_version)+1);
    assert.equal(await page.locator('[name="lines[0][quantity]"]').inputValue(),'','Reload requires a fresh physical recount');
    await page.locator('[name="lines[0][quantity]"]').fill('8');
    const submission=await page.locator('form[data-safe-form]').evaluate(form=>Object.fromEntries(new FormData(form)));
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.getByRole('button',{name:'Post stock operation'}).click()]); await settle(page);
    const afterCount=snapshot(); assert.equal(Number(afterCount['supply-1'].current_quantity),8);
    const replay=await page.request.post(fixture.url+'/inventory',{form:submission}); assert.equal(replay.status(),200);
    assert.deepEqual(snapshot(),afterCount,'Identical stock replay must not post again');
    await shot(page,'stock-operation-history');
    results.push({state:'stock-runtime-effects',missingReasonNoWrites:true,staleNoWrites:true,reloadedCount:8,duplicateNoWrites:true,before:beforeStock,after:afterCount});

    await go(page,'/expenses/create'); await page.locator('#description').fill('Unsaved browser fixture invoice');
    page.removeAllListeners('dialog'); let unsaved=false;
    page.on('dialog',async dialog=>{if(dialog.type()==='beforeunload'){unsaved=true;await dialog.dismiss();}else await dialog.accept();});
    await page.locator('form .form-actions a').click().catch(()=>{});
    assert(unsaved,'Unsaved edit warning'); assert(page.url().endsWith('/expenses/create'));
    page.removeAllListeners('dialog'); page.on('dialog',dialog=>dialog.accept());
    await go(page,'/orders/create'); await page.locator('.catalog-card button').first().click();
    await Promise.all([page.waitForURL('**/orders/create/details'),page.getByRole('button',{name:'Continue to contact and pickup',exact:true}).click()]); await settle(page);
    await page.getByRole('button',{name:'Add a new customer here',exact:true}).click();
    await page.locator('#new-first').fill('BrowserInline'+Date.now()); await page.locator('#new-last').fill('Fixture'); await page.locator('#new-phone').fill('0917ABC4567');
    const beforeInline=snapshot();
    await page.getByRole('button',{name:'Save and select customer',exact:true}).click();
    await page.waitForFunction(()=>!document.querySelector('[x-data^="staffCustomerPicker"]')._x_dataStack[0].creating);
    assert(await page.locator('#new-phone-error').isVisible()); assert.equal(await page.locator('#new-phone').inputValue(),'0917ABC4567'); assert.deepEqual(snapshot(),beforeInline);
    await shot(page,'inline-phone-error');
    await page.locator('#new-phone').fill('+63 32 234 5678');
    await page.route('**/orders/create/customer',async route=>{await new Promise(resolve=>setTimeout(resolve,800));await route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({message:'Temporary connection failure. Please retry.'})});});
    await page.getByRole('button',{name:'Save and select customer',exact:true}).click();
    assert(await page.getByRole('button',{name:'Saving customer…',exact:true}).isDisabled());
    await page.waitForFunction(()=>!document.querySelector('[x-data^="staffCustomerPicker"]')._x_dataStack[0].creating);
    assert(await page.getByText('Temporary connection failure. Please retry.',{exact:true}).isVisible()); assert.deepEqual(snapshot(),beforeInline);
    await shot(page,'inline-retry-error'); await page.unroute('**/orders/create/customer');
    await page.getByRole('button',{name:'Save and select customer',exact:true}).click();
    await page.waitForFunction(()=>document.querySelector('[name=customer_id]').value!=='');
    const afterInline=snapshot(); assert.equal(afterInline.customers.count,beforeInline.customers.count+1);
    results.push({state:'inline-retry-effects',invalidNoWrites:true,networkFailureNoWrites:true,busyDisabled:true,retryCreatedExactlyOne:true});
    await shot(page,'inline-valid-landline');
    await page.getByRole('button',{name:'Add a new customer here',exact:true}).click();
    await page.locator('#new-first').fill('SessionCopyMe'); await page.locator('#new-last').fill('Preserve'); await page.locator('#new-phone').fill('09171234567');
    await context.clearCookies();
    const expired=page.waitForResponse(response=>response.url().endsWith('/orders/create/customer'));
    await page.getByRole('button',{name:'Save and select customer',exact:true}).click(); assert.equal((await expired).status(),419);
    await page.waitForFunction(()=>!document.querySelector('[x-data^="staffCustomerPicker"]')._x_dataStack[0].creating);
    assert.equal(await page.locator('#new-first').inputValue(),'SessionCopyMe'); assert(await page.getByText('Your session expired.',{exact:false}).isVisible()); assert.deepEqual(snapshot(),afterInline);
    await shot(page,'inline-expired-session'); await context.close();

    const guestContext=await browser.newContext({viewport:{width:390,height:844}}); const guest=await guestContext.newPage();
    await go(guest,'/'); await guest.locator('a[aria-label^="Select "]').first().click(); await settle(guest);
    await guest.locator('[name$="[themes]"]').fill('Uploaded reference preserved');
    await guest.locator('input[type=file]').setInputFiles({name:'design-reference.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII=','base64')});
    await guest.getByText('Reference photos saved with this package draft.',{exact:true}).waitFor();
    await guest.reload({waitUntil:'load'}); await settle(guest);
    assert(await guest.getByText('Remove saved reference: design-reference.png',{exact:true}).isVisible());
    await Promise.all([guest.waitForURL(/saved_line=/),guest.getByRole('button',{name:'Save package to order',exact:true}).click()]); await settle(guest);
    await go(guest,'/order/details'); await guest.locator('[name=first_name]').fill('Reference'); await guest.locator('[name=last_name]').fill('Buyer'); await guest.locator('[name=phone_number]').fill('bad contact');
    await guest.locator('[name=pickup_date]').fill(await guest.locator('[name=pickup_date]').getAttribute('min')); await guest.locator('[name=pickup_time]').fill('15:00');
    await Promise.all([guest.waitForNavigation({waitUntil:'load'}),guest.getByRole('button',{name:'Submit order & continue',exact:true}).click()]); await settle(guest);
    await shot(guest,'public-upload-contact-error');
    await Promise.all([guest.waitForURL(fixture.url+'/'),guest.getByRole('button',{name:'Back to packages',exact:true}).click()]);await settle(guest);
    assert(await guest.getByText('Reference: design-reference.png',{exact:false}).isVisible());
    await guest.locator('.bag-line a').filter({hasText:'Edit'}).click();await settle(guest);
    assert(await guest.getByText('Remove saved reference: design-reference.png',{exact:true}).isVisible());
    await shot(guest,'public-upload-association-preserved');
    results.push({state:'reference-draft-effects',uploadSaved:true,refreshPreserved:true,invalidContactAssociationPreserved:true});
    await guestContext.close();
    fs.writeFileSync(path.join(directory,'evidence/browser-interactions.json'),JSON.stringify({outcome:'All interaction and server-effect assertions passed',results},null,2));
    console.log(JSON.stringify({outcome:'PASS',screenshots:results.filter(r=>r.screenshot).length,checks:results.filter(r=>!r.screenshot).length}));
} finally { await browser.close(); }
