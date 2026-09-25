import asyncio
import os
import re
from playwright import async_api
from playwright.async_api import expect

async def run_test():
    pw = None
    browser = None
    context = None

    try:
        pw = await async_api.async_playwright().start()
        browser = await pw.chromium.launch(headless=True)
        context = await browser.new_context()
        context.set_default_timeout(15000)
        page = await context.new_page()

        fixture_path = os.path.abspath("testsprite_tests/fixtures/sample_cake.png")

        # -> navigate
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Select the 'Classic Chocolate Dream Cake' product
        await page.get_by_role("heading", name="Classic Chocolate Dream Cake").click()
        
        # -> Select the 'Custom Buttercream Birthday Cake' product
        await page.get_by_role("heading", name="Custom Buttercream Birthday Cake").click()

        # -> Fill customer contact info
        await page.locator('input[name="first_name"]').fill("Clara")
        await page.locator('input[name="last_name"]').fill("Reyes")
        await page.locator('input[name="phone_number"]').fill("09179876543")

        # -> Add customization note
        notes_area = page.locator('textarea[name="notes_text"]')
        if await notes_area.is_visible():
            await notes_area.fill("Birthday celebration note with custom gold candles")

        # -> Attach reference image fixture
        file_input = page.locator("input[name=\"images\\[\\]\"]")
        await file_input.scroll_into_view_if_needed()
        await file_input.set_input_files(fixture_path)

        # -> Submit the order
        submit_btn = page.get_by_role("button", name="🎂 Submit Order Request")
        await submit_btn.scroll_into_view_if_needed()
        await submit_btn.click()

        # --> Assertions to verify final state
        await expect(page).to_have_url(re.compile(r"/order/success"), timeout=15000)
        await expect(page.get_by_role("main")).to_contain_text("Current Order Status: Pending")
        await expect(page.locator("text=Order Reference:")).to_be_visible(timeout=15000)
        print("TC002 passed successfully!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())