import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = 'C:\\Users\\User\\Desktop\\IT12_Project\\docs\\qa\\2026-10-05\\consolidated-investigation\\screenshots';
const evidenceDir = 'C:\\Users\\User\\Desktop\\IT12_Project\\docs\\qa\\2026-10-05\\consolidated-investigation\\evidence';

if (!fs.existsSync(screenshotDir)) fs.mkdirSync(screenshotDir, { recursive: true });
if (!fs.existsSync(evidenceDir)) fs.mkdirSync(evidenceDir, { recursive: true });

class CDPClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.id = 1;
        this.callbacks = new Map();
    }
    async connect() {
        this.ws = new WebSocket(this.wsUrl);
        await new Promise((resolve, reject) => {
            this.ws.onopen = resolve;
            this.ws.onerror = reject;
        });
        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.id && this.callbacks.has(data.id)) {
                const { resolve, reject } = this.callbacks.get(data.id);
                this.callbacks.delete(data.id);
                if (data.error) reject(data.error);
                else resolve(data.result);
            }
        };
    }
    send(method, params = {}) {
        const id = this.id++;
        return new Promise((resolve, reject) => {
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }
}

async function startChrome(port, userDataDir) {
    const chrome = spawn(chromePath, [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${userDataDir}`,
        'about:blank'
    ]);
    let wsUrl = null;
    for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 200));
        try {
            const res = await fetch(`http://127.0.0.1:${port}/json/list`);
            const tabs = await res.json();
            const pageTab = tabs.find(t => t.type === 'page');
            if (pageTab && pageTab.webSocketDebuggerUrl) {
                wsUrl = pageTab.webSocketDebuggerUrl;
                break;
            }
        } catch (e) {}
    }
    if (!wsUrl) {
        chrome.kill();
        throw new Error(`Failed to connect to Chrome on port ${port}`);
    }
    const client = new CDPClient(wsUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('DOM.enable');
    return { chrome, client };
}

const VIEWPORTS = [
    { width: 360, height: 780, mobile: true },
    { width: 390, height: 844, mobile: true },
    { width: 430, height: 932, mobile: true },
    { width: 768, height: 1024, mobile: true },
    { width: 1024, height: 768, mobile: false },
    { width: 1440, height: 900, mobile: false }
];

async function setViewport(client, width, height, mobile = false) {
    await client.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile
    });
}

async function captureScreen(client, filename) {
    const screenshot = await client.send('Page.captureScreenshot', { format: 'png' });
    const fullPath = path.join(screenshotDir, filename);
    fs.writeFileSync(fullPath, Buffer.from(screenshot.data, 'base64'));
    return fullPath;
}

async function measureDOM(client) {
    const res = await client.send('Runtime.evaluate', {
        expression: `(() => {
            const doc = document.documentElement;
            const body = document.body;
            const hasHorizontalScroll = doc.scrollWidth > doc.clientWidth;
            
            // Measure tables and scroll regions
            const tableScrolls = [...document.querySelectorAll('.table-scroll, .workspace-table')].map(el => ({
                className: el.className,
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
                isScrollable: el.scrollWidth > el.clientWidth
            }));

            // Catalog columns
            const catalog = document.querySelector('.store-catalog');
            const catalogCols = catalog ? getComputedStyle(catalog).gridTemplateColumns : null;

            // Touch targets
            const buttonsAndLinks = [...document.querySelectorAll('button, a.ui-button, a[class*="px-"]')];
            const smallTargets = buttonsAndLinks.filter(el => {
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && (rect.width < 40 || rect.height < 40);
            }).map(el => ({ text: el.textContent?.trim()?.slice(0, 30), w: el.offsetWidth, h: el.offsetHeight }));

            // Skip link
            const skipLink = document.querySelector('.skip-link');

            // Headings
            const h1 = document.querySelector('h1')?.textContent?.trim();

            return {
                url: window.location.href,
                title: document.title,
                docScrollWidth: doc.scrollWidth,
                docClientWidth: doc.clientWidth,
                hasHorizontalScroll,
                tableScrolls,
                catalogCols,
                smallTargetsCount: smallTargets.length,
                smallTargetsSample: smallTargets.slice(0, 3),
                hasSkipLink: Boolean(skipLink),
                h1
            };
        })()`,
        returnByValue: true
    });
    return res.result.value;
}

async function main() {
    const results = [];
    console.log('--- Starting Section A Browser QA Investigation ---');

    // 1. PUBLIC STORE SESSION
    console.log('\n[1/4] Auditing Public Storefront...');
    const publicProfile = path.join(process.env.TEMP, 'chrome-qa-public-' + Date.now());
    const { chrome: pubChrome, client: pubClient } = await startChrome(9225, publicProfile);

    try {
        // A. Public Catalog at all 6 viewports
        for (const vp of VIEWPORTS) {
            await setViewport(pubClient, vp.width, vp.height, vp.mobile);
            await pubClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(pubClient);
            const fn = `public-catalog-${vp.width}px.png`;
            await captureScreen(pubClient, fn);
            results.push({
                suite: 'public',
                screen: 'catalog',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Catalog @ ${vp.width}px: cols="${metrics.catalogCols}", overflow=${metrics.hasHorizontalScroll}`);
        }

        // B. Public Catalog with Enlarged Text (32px root font / 200%) at 360px
        await setViewport(pubClient, 360, 780, true);
        await pubClient.send('Runtime.evaluate', {
            expression: `document.documentElement.style.fontSize = '32px';`
        });
        await new Promise(r => setTimeout(r, 600));
        const enlargedMetrics = await measureDOM(pubClient);
        const enlargedFn = `public-catalog-360px-enlarged-text.png`;
        await captureScreen(pubClient, enlargedFn);
        results.push({
            suite: 'public',
            screen: 'catalog_enlarged_text',
            viewport: 360,
            status: enlargedMetrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
            screenshot: enlargedFn,
            metrics: enlargedMetrics
        });
        console.log(`  Catalog @ 360px (Enlarged 200%): cols="${enlargedMetrics.catalogCols}", overflow=${enlargedMetrics.hasHorizontalScroll}`);
        // Reset font size
        await pubClient.send('Runtime.evaluate', { expression: `document.documentElement.style.fontSize = '';` });

        // C. Public Customization Stage (/packages/1/customize/qa-line-1)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(pubClient, vp.width, vp.height, vp.mobile);
            await pubClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/packages/1/customize/qa-line-1' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(pubClient);
            const fn = `public-customize-${w}px.png`;
            await captureScreen(pubClient, fn);
            results.push({
                suite: 'public',
                screen: 'customize',
                viewport: w,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Customize @ ${w}px: overflow=${metrics.hasHorizontalScroll}`);
        }

        // D. Public Contact Details Stage (/order/details)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(pubClient, vp.width, vp.height, vp.mobile);
            await pubClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/order/details' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(pubClient);
            const fn = `public-details-${w}px.png`;
            await captureScreen(pubClient, fn);
            results.push({
                suite: 'public',
                screen: 'order_details',
                viewport: w,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Order Details @ ${w}px: overflow=${metrics.hasHorizontalScroll}`);
        }

        // E. Public Payment Page (Order 2 - Pending, Order 5 - Confirmed)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(pubClient, vp.width, vp.height, vp.mobile);
            // Order 2 pending token
            await pubClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/order/payment/3e8d5c9cbf805ead8efe320e18647bea4601046368a675d7459361b68dab0327' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(pubClient);
            const fn = `public-payment-pending-${w}px.png`;
            await captureScreen(pubClient, fn);
            results.push({
                suite: 'public',
                screen: 'payment_pending',
                viewport: w,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Payment Pending @ ${w}px: overflow=${metrics.hasHorizontalScroll}`);
        }

        // F. Login Page
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(pubClient, vp.width, vp.height, vp.mobile);
            await pubClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/login' });
            await new Promise(r => setTimeout(r, 1000));
            const metrics = await measureDOM(pubClient);
            const fn = `public-login-${w}px.png`;
            await captureScreen(pubClient, fn);
            results.push({
                suite: 'public',
                screen: 'login',
                viewport: w,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Login @ ${w}px: overflow=${metrics.hasHorizontalScroll}`);
        }

    } finally {
        pubChrome.kill();
    }

    // 2. OWNER STAFF SESSION
    console.log('\n[2/4] Auditing Staff Workspace (Owner Role)...');
    const ownerProfile = path.join(process.env.TEMP, 'chrome-qa-owner-' + Date.now());
    const { chrome: ownerChrome, client: ownerClient } = await startChrome(9226, ownerProfile);

    try {
        // Authenticate Owner
        await setViewport(ownerClient, 1440, 900, false);
        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/login' });
        await new Promise(r => setTimeout(r, 1000));
        await ownerClient.send('Runtime.evaluate', {
            expression: `(() => {
                document.querySelector('input[name="email"]').value = 'owner@alingchona.local';
                document.querySelector('input[name="password"]').value = 'password123';
                document.querySelector('form').submit();
            })()`
        });
        await new Promise(r => setTimeout(r, 2000));

        // A. Owner Dashboard across all 6 viewports
        for (const vp of VIEWPORTS) {
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/dashboard' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-dashboard-${vp.width}px.png`;
            await captureScreen(ownerClient, fn);
            results.push({
                suite: 'staff_owner',
                screen: 'dashboard',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
            console.log(`  Owner Dashboard @ ${vp.width}px: overflow=${metrics.hasHorizontalScroll}`);
        }

        // B. Mobile Navigation Drawer (Focus containment, Escape close) at 390px
        await setViewport(ownerClient, 390, 844, true);
        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/dashboard' });
        await new Promise(r => setTimeout(r, 1000));
        // Open drawer
        await ownerClient.send('Runtime.evaluate', {
            expression: `document.querySelector('button[aria-label="Open navigation"]').click();`
        });
        await new Promise(r => setTimeout(r, 500));
        const drawerOpenMetrics = await ownerClient.send('Runtime.evaluate', {
            expression: `({
                isOpen: document.querySelector('#staff-navigation')?.dataset?.open === 'true',
                activeElement: document.activeElement?.tagName,
                bodyOverflow: document.body.style.overflow
            })`,
            returnByValue: true
        });
        const drawerFn = `staff-owner-mobile-drawer-open-390px.png`;
        await captureScreen(ownerClient, drawerFn);
        console.log(`  Mobile Drawer Open @ 390px: open=${drawerOpenMetrics.result.value.isOpen}, overflow=${drawerOpenMetrics.result.value.bodyOverflow}`);

        // Press Escape key
        await ownerClient.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
        await new Promise(r => setTimeout(r, 500));
        const drawerClosedMetrics = await ownerClient.send('Runtime.evaluate', {
            expression: `({
                isOpen: document.querySelector('#staff-navigation')?.dataset?.open === 'true',
                activeElement: document.activeElement?.getAttribute('aria-label') || document.activeElement?.tagName,
                bodyOverflow: document.body.style.overflow
            })`,
            returnByValue: true
        });
        console.log(`  Mobile Drawer After Escape: open=${drawerClosedMetrics.result.value.isOpen}, focusRestored=${drawerClosedMetrics.result.value.activeElement}`);

        results.push({
            suite: 'staff_owner',
            screen: 'mobile_drawer_escape',
            viewport: 390,
            status: (!drawerClosedMetrics.result.value.isOpen && drawerOpenMetrics.result.value.isOpen) ? 'pass' : 'fail',
            screenshot: drawerFn,
            drawerMetrics: { open: drawerOpenMetrics.result.value, closed: drawerClosedMetrics.result.value }
        });

        // C. Pickup Schedule (/pickup-schedule)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/pickup-schedule' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-schedule-${w}px.png`;
            await captureScreen(ownerClient, fn);
            results.push({ suite: 'staff_owner', screen: 'schedule', viewport: w, status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass', screenshot: fn, metrics });
        }

        // D. Orders Listing (/orders) across all 6 viewports (checking local table scrolling)
        for (const vp of VIEWPORTS) {
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/orders' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-orders-${vp.width}px.png`;
            await captureScreen(ownerClient, fn);
            const tableScroll = metrics.tableScrolls.find(t => t.className.includes('table-scroll'));
            console.log(`  Orders @ ${vp.width}px: pageOverflow=${metrics.hasHorizontalScroll}, tableScrollable=${tableScroll?.isScrollable}`);
            results.push({
                suite: 'staff_owner',
                screen: 'orders_index',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
        }

        // E. Order Show (/orders/1)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/orders/1' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-order-show-${w}px.png`;
            await captureScreen(ownerClient, fn);
            results.push({ suite: 'staff_owner', screen: 'order_show', viewport: w, status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass', screenshot: fn, metrics });
        }

        // F. Customers Listing (/customers)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/customers' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-customers-${w}px.png`;
            await captureScreen(ownerClient, fn);
            results.push({ suite: 'staff_owner', screen: 'customers_index', viewport: w, status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass', screenshot: fn, metrics });
        }

        // G. Products Listing (/products)
        for (const w of [390, 1440]) {
            const vp = VIEWPORTS.find(v => v.width === w);
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/products' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-products-${w}px.png`;
            await captureScreen(ownerClient, fn);
            results.push({ suite: 'staff_owner', screen: 'products_index', viewport: w, status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass', screenshot: fn, metrics });
        }

        // H. Supplies / Inventory Workspace (/supplies) across all 6 viewports
        for (const vp of VIEWPORTS) {
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/supplies' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-inventory-${vp.width}px.png`;
            await captureScreen(ownerClient, fn);
            const wsTable = metrics.tableScrolls.find(t => t.className.includes('workspace-table') || t.className.includes('table-scroll'));
            console.log(`  Inventory @ ${vp.width}px: pageOverflow=${metrics.hasHorizontalScroll}, tableScrollable=${wsTable?.isScrollable}`);
            results.push({
                suite: 'staff_owner',
                screen: 'supplies_index',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
        }

        // I. Inventory Dedicated Forms (Receive, Usage, Stocktake, History)
        for (const formType of ['receipt', 'usage', 'stocktake']) {
            await setViewport(ownerClient, 1440, 900, false);
            await ownerClient.send('Page.navigate', { url: `http://127.0.0.1:8000/inventory/create/${formType}` });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-inventory-${formType}-1440px.png`;
            await captureScreen(ownerClient, fn);
            results.push({ suite: 'staff_owner', screen: `inventory_${formType}`, viewport: 1440, status: 'pass', screenshot: fn, metrics });
        }
        // Also capture receive stock at mobile 390px
        await setViewport(ownerClient, 390, 844, true);
        await ownerClient.send('Page.navigate', { url: `http://127.0.0.1:8000/inventory/create/receipt` });
        await new Promise(r => setTimeout(r, 1200));
        await captureScreen(ownerClient, `staff-owner-inventory-receipt-390px.png`);

        // J. Expenses Workspace (/expenses) across all 6 viewports
        for (const vp of VIEWPORTS) {
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/expenses' });
            await new Promise(r => setTimeout(r, 1200));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-expenses-${vp.width}px.png`;
            await captureScreen(ownerClient, fn);
            const wsTable = metrics.tableScrolls.find(t => t.className.includes('workspace-table') || t.className.includes('table-scroll'));
            console.log(`  Expenses @ ${vp.width}px: pageOverflow=${metrics.hasHorizontalScroll}, tableScrollable=${wsTable?.isScrollable}`);
            results.push({
                suite: 'staff_owner',
                screen: 'expenses_index',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
        }

        // K. Expense Create and History
        await setViewport(ownerClient, 1440, 900, false);
        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/expenses/create' });
        await new Promise(r => setTimeout(r, 1200));
        await captureScreen(ownerClient, `staff-owner-expenses-create-1440px.png`);

        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/expenses/history' });
        await new Promise(r => setTimeout(r, 1200));
        await captureScreen(ownerClient, `staff-owner-expenses-history-1440px.png`);

        // L. Reports Workspace (/reports) across all 6 viewports
        for (const vp of VIEWPORTS) {
            await setViewport(ownerClient, vp.width, vp.height, vp.mobile);
            await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/reports?mode=month&month=2026-09' });
            await new Promise(r => setTimeout(r, 1500));
            const metrics = await measureDOM(ownerClient);
            const fn = `staff-owner-reports-${vp.width}px.png`;
            await captureScreen(ownerClient, fn);
            console.log(`  Reports @ ${vp.width}px: pageOverflow=${metrics.hasHorizontalScroll}`);
            results.push({
                suite: 'staff_owner',
                screen: 'reports_index',
                viewport: vp.width,
                status: metrics.hasHorizontalScroll ? 'fail_overflow' : 'pass',
                screenshot: fn,
                metrics
            });
        }

        // M. Reports Drill-down Records (/reports/records)
        await setViewport(ownerClient, 1440, 900, false);
        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/reports/records?mode=month&month=2026-09&metric=sales' });
        await new Promise(r => setTimeout(r, 1200));
        await captureScreen(ownerClient, `staff-owner-reports-drilldown-1440px.png`);

        // N. Users Management (/users) for Owner
        await ownerClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/users' });
        await new Promise(r => setTimeout(r, 1200));
        await captureScreen(ownerClient, `staff-owner-users-1440px.png`);

    } finally {
        ownerChrome.kill();
    }

    // 3. ASSISTANT STAFF SESSION
    console.log('\n[3/4] Auditing Staff Workspace (Assistant Role)...');
    const assistantProfile = path.join(process.env.TEMP, 'chrome-qa-assistant-' + Date.now());
    const { chrome: asstChrome, client: asstClient } = await startChrome(9227, assistantProfile);

    try {
        // Authenticate Assistant
        await setViewport(asstClient, 1440, 900, false);
        await asstClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/login' });
        await new Promise(r => setTimeout(r, 1000));
        await asstClient.send('Runtime.evaluate', {
            expression: `(() => {
                document.querySelector('input[name="email"]').value = 'assistant@alingchona.local';
                document.querySelector('input[name="password"]').value = 'password123';
                document.querySelector('form').submit();
            })()`
        });
        await new Promise(r => setTimeout(r, 2000));

        // A. Assistant Dashboard: check sidebar lacks Admin / Users
        const asstDashboardMetrics = await asstClient.send('Runtime.evaluate', {
            expression: `({
                hasUsersLink: Boolean(document.querySelector('a[href*="/users"]')),
                userGreeting: document.querySelector('h1')?.textContent?.trim(),
                userBadge: document.querySelector('header span.capitalize')?.textContent?.trim()
            })`,
            returnByValue: true
        });
        await captureScreen(asstClient, `staff-assistant-dashboard-1440px.png`);
        console.log(`  Assistant Dashboard: badge="${asstDashboardMetrics.result.value.userBadge}", hasUsersLink=${asstDashboardMetrics.result.value.hasUsersLink}`);

        // B. Assistant visits /users -> expected 403 Forbidden
        await asstClient.send('Page.navigate', { url: 'http://127.0.0.1:8000/users' });
        await new Promise(r => setTimeout(r, 1000));
        const asstUsersMetrics = await asstClient.send('Runtime.evaluate', {
            expression: `({
                title: document.title,
                bodyText: document.body.innerText.slice(0, 100)
            })`,
            returnByValue: true
        });
        await captureScreen(asstClient, `staff-assistant-users-403-forbidden.png`);
        console.log(`  Assistant accessing /users: title="${asstUsersMetrics.result.value.title}"`);

    } finally {
        asstChrome.kill();
    }

    // Save final evidence index
    fs.writeFileSync(path.join(evidenceDir, 'viewport-state.json'), JSON.stringify(results, null, 2));
    console.log(`\nCompleted Section A Browser QA! Recorded ${results.length} rendered states and screenshots.`);
}

main().catch(console.error);
