import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = 'C:\\Users\\User\\Desktop\\IT12_Project\\docs\\qa\\2026-10-05\\consolidated-investigation\\screenshots';

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

async function captureScreen(client, filename) {
    const screenshot = await client.send('Page.captureScreenshot', { format: 'png' });
    const fullPath = path.join(screenshotDir, filename);
    fs.writeFileSync(fullPath, Buffer.from(screenshot.data, 'base64'));
    console.log('Captured:', filename, 'size:', fs.statSync(fullPath).size);
}

async function main() {
    const profile = path.join(process.env.TEMP, 'chrome-stage-qa-' + Date.now());
    const { chrome, client } = await startChrome(9228, profile);

    try {
        // 1. Visit catalog, click first "Select package"
        await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8000/' });
        await new Promise(r => setTimeout(r, 1200));

        // Click first select package button
        await client.send('Runtime.evaluate', {
            expression: `document.querySelector('a[aria-label^="Select"]').click();`
        });
        await new Promise(r => setTimeout(r, 1500));

        // Now on customize page!
        await captureScreen(client, 'public-customize-1440px.png');

        // Set to mobile 390px and capture
        await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await new Promise(r => setTimeout(r, 600));
        await captureScreen(client, 'public-customize-390px.png');

        // Now save package to order to populate order bag
        await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
        await client.send('Runtime.evaluate', {
            expression: `document.querySelector('form.customize-form').submit();`
        });
        await new Promise(r => setTimeout(r, 1500));

        // Now on catalog page with "Your order" bag populated!
        await captureScreen(client, 'public-catalog-with-order-bag-1440px.png');

        // Click "Continue to contact and pickup"
        await client.send('Runtime.evaluate', {
            expression: `document.querySelector('a[href*="/order/details"]').click();`
        });
        await new Promise(r => setTimeout(r, 1500));

        // Now on contact details stage!
        await captureScreen(client, 'public-details-1440px.png');

        await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await new Promise(r => setTimeout(r, 600));
        await captureScreen(client, 'public-details-390px.png');

        // Test field-level validation on /order/details: click submit with empty fields
        await client.send('Runtime.evaluate', {
            expression: `document.querySelector('form button[type="submit"], form button.primary').click();`
        });
        await new Promise(r => setTimeout(r, 1500));
        await captureScreen(client, 'public-details-validation-errors-390px.png');

    } finally {
        chrome.kill();
    }
}

main().catch(console.error);
