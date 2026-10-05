import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const userDataDir = path.join(process.env.TEMP, 'chrome-auth-' + Date.now());

const chrome = spawn(chromePath, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-debugging-port=9224',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
]);

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

async function main() {
    try {
        let wsUrl = null;
        for (let i = 0; i < 30; i++) {
            await new Promise(r => setTimeout(r, 200));
            try {
                const res = await fetch('http://127.0.0.1:9224/json/list');
                const tabs = await res.json();
                const pageTab = tabs.find(t => t.type === 'page');
                if (pageTab && pageTab.webSocketDebuggerUrl) {
                    wsUrl = pageTab.webSocketDebuggerUrl;
                    break;
                }
            } catch (e) {}
        }
        if (!wsUrl) throw new Error('No page tab found');

        const client = new CDPClient(wsUrl);
        await client.connect();
        await client.send('Page.enable');
        await client.send('DOM.enable');
        await client.send('Emulation.setDeviceMetricsOverride', {
            width: 1440,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false
        });

        // Navigate to login
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8000/login' });
        await new Promise(r => setTimeout(r, 1000));

        // Fill form
        await client.send('Runtime.evaluate', {
            expression: `(() => {
                document.querySelector('input[name="email"]').value = 'owner@alingchona.local';
                document.querySelector('input[name="password"]').value = 'password123';
                document.querySelector('form').submit();
            })()`
        });

        await new Promise(r => setTimeout(r, 2000));

        const res = await client.send('Runtime.evaluate', {
            expression: `({
                url: window.location.href,
                title: document.title,
                heading: document.querySelector('h1')?.textContent?.trim()
            })`,
            returnByValue: true
        });

        console.log('Login result:', JSON.stringify(res.result.value));

        // Take dashboard screenshot
        const screenshot = await client.send('Page.captureScreenshot', { format: 'png' });
        const outPath = 'C:\\Users\\User\\Desktop\\IT12_Project\\docs\\qa\\2026-10-05\\consolidated-investigation\\screenshots\\test_owner_dashboard_1440.png';
        fs.writeFileSync(outPath, Buffer.from(screenshot.data, 'base64'));
        console.log('Saved dashboard screenshot, size:', fs.statSync(outPath).size);

    } catch (e) {
        console.error('Error:', e);
    } finally {
        chrome.kill();
    }
}

main();
