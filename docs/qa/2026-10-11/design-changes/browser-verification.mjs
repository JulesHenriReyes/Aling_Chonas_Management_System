import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const dir = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(dir,'preview-fixture.json')));
const base = 'http://127.0.0.1:8148';
const url = kind => `${base}/order/payment/${fixture.orders[kind].token}`;
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
const customer = await browser.newPage({viewport:{width:1440,height:900}});
const staff = await browser.newPage({viewport:{width:1440,height:900}});
const results = [];
const errors = [];
for (const page of [customer,staff]) page.on('pageerror', error => errors.push(error.message));
function check(ok, message) { if (!ok) throw new Error(message); results.push(message); }
async function assets(page) { await page.waitForFunction(() => !!window.Alpine && typeof tailwind !== 'undefined'); await page.evaluate(() => document.fonts.ready); }
async function capture(page,name) {
    await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'});});
    await page.screenshot({path:path.join(dir,name+'.png'),fullPage:true});
}
async function layout(page,label) {
    await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{}, {timeout:3000});
    const metrics = await page.evaluate(() => ({overflow:document.documentElement.scrollWidth-innerWidth,
        broken:Array.from(document.querySelectorAll('img')).filter(image=>image.complete && image.naturalWidth===0).length}));
    check(metrics.overflow<=1,`${label}: no horizontal overflow`);
    check(metrics.broken===0,`${label}: no broken images`);
}
async function submitChange(kind, theme, upload=false) {
    await customer.goto(url(kind)+'/design-changes/new'); await assets(customer);
    await customer.locator('[name$="[themes]"]').fill(theme);
    await customer.locator('[name$="[special_request]"]').fill('Happy birthday, Maria Alexandra! Please use small cream flowers and a neat border around the base.');
    if (upload) {
        await customer.locator('[data-design-upload]').setInputFiles({name:'Floral reference.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=','base64')});
        await customer.locator('[data-upload-status]').filter({hasText:'Photos uploaded.'}).waitFor();
        const response = await customer.request.get(await customer.locator('[data-staged-images] img').getAttribute('src'));
        check(response.status()===200 && response.headers()['cache-control'].includes('no-store'),'Draft photos are private and load correctly');
    }
    await customer.locator('[name=pause_acknowledged]').check();
    await Promise.all([customer.waitForURL(url(kind)+'#design-change'),customer.getByRole('button',{name:'Submit change request',exact:true}).click()]);
    await assets(customer);
    check(await customer.locator('#design-change').innerText().then(text=>text.includes('Awaiting review.')),'Customer submission creates a pending comparison');
}
try {
    for (const width of [1440,390]) {
        await customer.setViewportSize({width,height:width===1440?900:844});
        await customer.goto(url('deposit')); await assets(customer); await layout(customer,`Saved order ${width}`);
        check(await customer.getByRole('link',{name:'Request design change',exact:true}).count()===1,'Change request is beside saved design details');
        const place = await customer.evaluate(() => {
            const entry = document.querySelector('.design-entry').getBoundingClientRect();
            const details = document.querySelector('.order-package-row').getBoundingClientRect();
            const cancel = document.querySelector('[data-order-cancellation]').closest('section').getBoundingClientRect();
            return entry.bottom<=details.top && cancel.bottom<=document.querySelector('#contact-bakery-heading').closest('section').getBoundingClientRect().top;
        });
        check(place,`Button placement and cancellation layout retained at ${width}`);
        await capture(customer,`saved-order-${width}`);
        await customer.getByRole('link',{name:'Request design change',exact:true}).click(); await assets(customer); await layout(customer,`Editor ${width}`);
        await capture(customer,`editor-${width}`);
        await customer.locator('.design-current-summary summary').focus(); await customer.keyboard.press('Enter');
        check(await customer.locator('.design-current-summary').getAttribute('open')!==null,'Keyboard opens current design summary');
        const target = await customer.getByRole('button',{name:'Submit change request',exact:true}).boundingBox();
        check(target.height>=44 && target.width>100,`Submission target is at least 44px high at ${width}`);
    }
    await staff.goto(base+'/login'); await staff.locator('[name=email]').fill('assistant@example.test'); await staff.locator('[name=password]').fill('password123');
    await Promise.all([staff.waitForURL('**/dashboard'),staff.locator('button[type=submit]').click()]);
    await staff.goto(base+'/orders'); await assets(staff);
    await submitChange('deposit','Sage green and cream flowers',true);
    const row = staff.getByRole('row').filter({hasText:fixture.orders.deposit.number});
    await row.getByText('Design change · paused',{exact:true}).waitFor({timeout:15000});
    check(true,'Staff orders list notices a customer request without a manual refresh');
    await staff.goto(base+'/orders?queue=changes'); await assets(staff);
    check(await staff.getByRole('row').filter({hasText:fixture.orders.deposit.number}).count()===1,'Design Changes filter includes the pending request');
    await capture(staff,'queue-desktop');
    await staff.goto(base+'/orders/'+fixture.orders.deposit.id); await assets(staff);
    check(await staff.getByRole('button',{name:'Start Preparing (Baking)',exact:true}).count()===0,'Preparation button is held while review is pending');
    for (const width of [1440,390]) {
        await staff.setViewportSize({width,height:width===1440?900:844}); await layout(staff,`Staff review ${width}`);
        await capture(staff,`staff-review-${width}`);
        await staff.locator('#design-change').screenshot({path:path.join(dir,`staff-comparison-${width}.png`)});
        await customer.setViewportSize({width,height:width===1440?900:844}); await layout(customer,`Customer pending ${width}`); await capture(customer,`customer-pending-${width}`);
    }
    // Unsaved customer input is preserved when the reviewer makes a decision.
    await customer.locator('[data-order-cancellation] summary').click(); await customer.locator('[name=confirm_cancellation]').check();
    await staff.locator('form[action$="/approve"] [name=feasibility_confirmed]').check();
    await Promise.all([staff.waitForNavigation(),staff.getByRole('button',{name:'Approve design change',exact:true}).click()]);
    check(await staff.getByText('Sage green and cream flowers',{exact:true}).count()>=1,'Assistant approval updates the saved theme');
    await customer.locator('[data-live-notice]:not([hidden])').waitFor({timeout:15000});
    check(await customer.locator('[name=confirm_cancellation]').isChecked(),'Auto updates preserve an open cancellation confirmation');
    await customer.getByRole('button',{name:'Load the update',exact:true}).click(); await assets(customer);
    check(await customer.locator('#design-change').innerText().then(text=>text.includes('Latest change: Approved.')),'Customer receives the approval on their private link');
    check(await customer.locator('.order-package-row').count()===1,'Approval preserves the package layout');
    await capture(customer,'approved-mobile');
    await submitChange('preparing','Cream and gold with small leaves');
    await staff.goto(base+'/orders/'+fixture.orders.preparing.id); await assets(staff);
    check(await staff.getByRole('button',{name:'Mark Ready for Pickup',exact:true}).count()===0,'A request during preparation holds marking ready');
    await Promise.all([customer.waitForNavigation(),customer.getByRole('button',{name:'Withdraw change request',exact:true}).click()]);
    await staff.getByRole('button',{name:'Mark Ready for Pickup',exact:true}).waitFor({timeout:15000});
    check(true,'Withdrawing a request resumes preparation and updates staff automatically');
    await submitChange('decline','Fondant sculpted characters with three extra tiers');
    await staff.goto(base+'/orders/'+fixture.orders.decline.id); await assets(staff);
    await staff.locator('[data-design-decline] summary').click(); await staff.locator('[name=reason]').fill('This design requires a different package and extra payment. We can continue with the saved floral design.');
    await Promise.all([staff.waitForNavigation(),staff.getByRole('button',{name:'Decline with explanation',exact:true}).click()]);
    await customer.getByText('Latest change: Declined.',{exact:false}).waitFor({timeout:15000});
    check(await customer.getByText('This design requires a different package and extra payment. We can continue with the saved floral design.',{exact:true}).count()>=1,'Customer sees the reason for declining without manual refresh');
    await capture(customer,'declined-mobile');
    await submitChange('cancel','Lavender and white');
    await customer.locator('[data-order-cancellation] summary').click(); await customer.locator('[name=confirm_cancellation]').check();
    await Promise.all([customer.waitForNavigation(),customer.getByRole('button',{name:'Yes, cancel order',exact:true}).click()]);
    check(await customer.locator('#design-change').innerText().then(text=>text.includes('Latest change: Closed.')),'Order cancellation closes its pending design request');
    check(errors.length===0,`No browser script errors (${errors.join(', ')})`);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify(results,null,2));
    console.log(`${results.length} browser checks passed`);
} finally { await browser.close(); }
