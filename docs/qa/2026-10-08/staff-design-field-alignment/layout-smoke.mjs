import { chromium } from 'file:///C:/Users/User/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const css = fs.readFileSync(new URL('../../../..//public/css/bakery-ui.css', import.meta.url), 'utf8');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const markup = `
  <style>${css}</style>
  <div class="staff-ordering">
    <form class="catalog-selection-form">
      <div class="customize-main" style="width:100%;max-width:636px">
        <section class="item-section">
        <div class="staff-design-fields col-span-full" style="grid-column:1/-1">
          <div class="staff-design-field">
            <label for="theme-1">Theme and colors (optional)</label>
            <input id="theme-1" class="w-full">
          </div>
          <div class="staff-design-field">
            <label for="request-1">Design instructions and special requests (optional)</label>
            <textarea id="request-1" rows="2"></textarea>
          </div>
        </div>
        <div class="col-span-full" style="grid-column:1/-1"><input id="images-1" type="file"></div>
        </section>
      </div>
    </form>
  </div>`;
await page.setContent(markup);
const result = {};
for (const width of [390, 768, 1440]) {
  await page.setViewportSize({ width, height: 900 });
  result[width] = await page.evaluate(() => {
    const read = id => { const r = document.getElementById(id).getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, bottom: r.bottom }; };
    return { theme: read('theme-1'), request: read('request-1'), photos: read('images-1'), overflow: document.documentElement.scrollWidth - innerWidth };
  });
}
if (Math.abs(result[1440].theme.y - result[1440].request.y) > 1) throw new Error('Wide staff fields are not aligned.');
if (Math.abs(result[390].theme.x - result[390].request.x) > 1 || result[390].request.y < result[390].theme.bottom) throw new Error('Narrow staff fields do not stack.');
if (result[390].overflow > 1 || result[768].overflow > 1 || result[1440].overflow > 1) throw new Error('Layout overflows horizontally.');
await page.setContent(`<style>${css}</style><form class="catalog-selection-form"><section class="item-section"><div class="design-fields"><div><label for="p-theme">Theme and colors (optional)</label><input id="p-theme"></div><div><label for="p-request">Design instructions and special requests (optional)</label><textarea id="p-request" rows="2"></textarea></div></section></form>`);
for (const width of [390, 1440]) {
  await page.setViewportSize({ width, height: 900 });
  const publicLayout = await page.evaluate(() => {
    const read = id => { const r = document.getElementById(id).getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, bottom: r.bottom }; };
    return { theme: read('p-theme'), request: read('p-request'), fieldsDisplay: getComputedStyle(document.querySelector('.design-fields')).display };
  });
  result[`public-${width}`] = publicLayout;
  if (publicLayout.fieldsDisplay !== 'contents') throw new Error('Public design fields no longer participate in the original grid.');
  if (width === 1440 && Math.abs(publicLayout.theme.x - publicLayout.request.x) < 1) throw new Error('Public fields no longer retain their two-column layout.');
  if (width === 390 && Math.abs(publicLayout.theme.x - publicLayout.request.x) > 1) throw new Error('Public fields no longer stack on narrow screens.');
}
console.log(JSON.stringify(result, null, 2));
await browser.close();
