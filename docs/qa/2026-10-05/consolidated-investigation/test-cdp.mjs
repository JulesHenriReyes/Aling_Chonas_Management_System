import { spawn } from 'child_process';
import path from 'path';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const userDataDir = path.join(process.env.TEMP, 'chrome-cdp-' + Date.now());

const chrome = spawn(chromePath, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-debugging-port=9222',
    `--user-data-dir=${userDataDir}`,
    'http://127.0.0.1:8000/'
]);

async function main() {
    for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 200));
        try {
            const res = await fetch('http://127.0.0.1:9222/json/list');
            const tabs = await res.json();
            if (tabs && tabs.length > 0) {
                console.log('SUCCESS: Connected to Chrome tabs:', tabs.length);
                console.log('Tab URL:', tabs[0].url);
                console.log('WebSocket Debugger URL:', tabs[0].webSocketDebuggerUrl);
                chrome.kill();
                process.exit(0);
            }
        } catch (e) {}
    }
    console.log('Failed to connect to CDP');
    chrome.kill();
    process.exit(1);
}

main();
