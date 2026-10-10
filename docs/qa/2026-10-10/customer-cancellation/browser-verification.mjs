import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const evidence = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(fs.readFileSync(path.join(evidence, 'preview-fixture.json')));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const page = await browser.newPage();
const base = 'http://127.0.0.1:8146';
const results = [];
function check(condition, message) { if (!condition) throw new Error(message); results.push(message); }
const url = kind => `${base}/order/payment/${fixture.orders[kind].token}`;
try {
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: width === 1440 ? 900 : 844 });
        for (const kind of ['pending', 'approved', 'deposit', 'receipt']) {
            await page.goto(url(kind));
            await page.waitForFunction(() => typeof tailwind !== 'undefined' && !!window.Alpine);
            await page.evaluate(() => document.fonts.ready);
            const details = page.locator('details[data-order-cancellation]');
            if (kind === 'receipt') {
                check(await details.count() === 0, `Receipt reconciliation has no instant cancellation at ${width}`);
            } else {
                check(await details.count() === 1, `${kind} has cancellation at ${width}`);
                const layout = await page.evaluate(() => {
                    const section = document.querySelector('[aria-label="Order cancellation"]');
                    const help = document.querySelector('#contact-bakery-heading').closest('section');
                    const rect = section.getBoundingClientRect();
                    return { overflow: document.documentElement.scrollWidth - innerWidth, left: rect.left, right: rect.right,
                        afterMain: section.previousElementSibling.getBoundingClientRect().bottom <= rect.top,
                        beforeHelp: rect.bottom <= help.getBoundingClientRect().top };
                });
                check(layout.overflow <= 1 && layout.left >= 0 && layout.right <= width, `${kind} has no overflow at ${width}`);
                check(layout.afterMain && layout.beforeHelp, `${kind} cancellation is below the main card and above help at ${width}`);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: path.join(evidence, `${kind}-${width}-closed.png`), fullPage: true });
                await details.locator('summary').focus();
                await page.keyboard.press('Enter');
                check(await details.getAttribute('open') !== null, `Keyboard expands ${kind} confirmation at ${width}`);
                check(await page.locator('[name=confirm_cancellation]').isVisible(), `Confirmation checkbox visible at ${width}`);
                check(await page.locator('[name=confirm_cancellation]').evaluate(el => el.required), 'Explicit confirmation required');
                if (kind === 'deposit') check(await details.innerText().then(text => text.includes('₱500.00') && text.includes('It will not be refunded.')), 'Verified deposit warning names the retained amount');
                const button = page.getByRole('button', { name: 'Yes, cancel order', exact: true });
                const rect = await button.boundingBox();
                check(rect.height >= 44 && rect.x >= 0 && rect.x + rect.width <= width, `Confirmation button fits with 44px target at ${width}`);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: path.join(evidence, `${kind}-${width}-open.png`), fullPage: true });
            }
        }
    }
    // Exercise actual CSRF/session-protected cancellation and repeat submission.
    await page.goto(url('deposit'));
    await page.locator('details[data-order-cancellation] summary').click();
    const csrf = await page.locator('details[data-order-cancellation] [name=_token]').inputValue();
    await page.locator('[name=confirm_cancellation]').check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Yes, cancel order', exact: true }).click()]);
    check(await page.getByRole('status').filter({ hasText: 'Your order is cancelled.' }).count() === 1, 'Verified deposit cancels immediately through real browser POST');
    check(await page.locator('details[data-order-cancellation]').count() === 0, 'Cancelled order removes the cancellation control');
    await page.screenshot({ path: path.join(evidence, 'cancelled-390.png'), fullPage: true });
    const replay = await page.request.post(`${url('deposit')}/cancel`, { form: { _token: csrf, confirm_cancellation: '1' }, maxRedirects: 0 });
    check(replay.status() === 302, 'Repeat confirmed cancellation safely redirects');
    const staff = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await staff.goto(`${base}/login`);
    await staff.locator('[name=email]').fill('owner@example.test');
    await staff.locator('[name=password]').fill('password123');
    await Promise.all([staff.waitForURL('**/dashboard'), staff.locator('button[type=submit]').click()]);
    await staff.goto(`${base}/orders`);
    const staffRow = staff.getByRole('row').filter({ hasText: fixture.orders.pending.number });
    check(await staffRow.getByText('Awaiting staff confirmation', { exact: true }).count() === 1, 'Staff sees pending order before customer cancellation');
    await page.goto(url('pending'));
    await page.locator('details[data-order-cancellation] summary').click();
    await page.locator('[name=confirm_cancellation]').check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Yes, cancel order', exact: true }).click()]);
    check(await page.getByRole('status').filter({ hasText: 'Your order is cancelled.' }).count() === 1, 'Unpaid order cancels immediately through real browser POST');
    await staffRow.getByText('Cancelled', { exact: true }).waitFor({ timeout: 15000 });
    check(true, 'Staff orders page reflects customer cancellation without manual refresh');
    await staff.close();
    fs.writeFileSync(path.join(evidence, 'browser-results.json'), JSON.stringify(results, null, 2));
    console.log(`${results.length} browser checks passed`);
} finally { await browser.close(); }
