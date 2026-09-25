import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

async def run_test():
    pw = None
    browser = None
    context = None

    try:
        # Start a Playwright session in asynchronous mode
        pw = await async_api.async_playwright().start()

        # Launch a Chromium browser in headless mode with custom arguments
        browser = await pw.chromium.launch(
            headless=True,
            args=[
                "--window-size=1280,720",
                "--disable-dev-shm-usage",
                "--ipc=host",
            ],
        )

        # Create a new browser context (like an incognito window)
        context = await browser.new_context()
        # Wider default timeout to match the agent's DOM-stability budget;
        # auto-waiting Playwright APIs (expect, locator.wait_for) inherit this.
        context.set_default_timeout(15000)

        # Open a new page in the browser context
        page = await context.new_page()

        # Interact with the page elements to simulate user flow
        # -> navigate
        await page.goto("http://localhost:8000/")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Scroll the page to reveal the 'Classic Chocolate Dream Cake' product and the order form controls so interactive elements appear.
        await page.mouse.wheel(0, 300)
        
        # -> Select the 'Classic Chocolate Dream Cake' checkbox, wait for the UI to update, then select the 'Custom Fondant Celebration Cake' checkbox.
        # checkbox
        elem = page.locator(".rounded").first
        await elem.click(timeout=10000)
        
        # -> Select the 'Classic Chocolate Dream Cake' checkbox, wait for the UI to update, then select the 'Custom Fondant Celebration Cake' checkbox.
        # checkbox
        elem = page.locator("div:nth-child(3) > .flex > .rounded")
        await elem.click(timeout=10000)
        
        # -> Fill 'First Name', 'Last Name', and 'Mobile Phone Number' with valid values, then click the 'Submit Order Request' button.
        # first_name text field
        elem = page.locator("input[name=\"first_name\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Ana")
        
        # -> Fill 'First Name', 'Last Name', and 'Mobile Phone Number' with valid values, then click the 'Submit Order Request' button.
        # last_name text field
        elem = page.locator("input[name=\"last_name\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Santos")
        
        # -> Fill 'First Name', 'Last Name', and 'Mobile Phone Number' with valid values, then click the 'Submit Order Request' button.
        # e.g. 0917-123-4567 or 09187654321 tel field
        elem = page.get_by_role("textbox", name="e.g. 0917-123-4567 or")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("09171234567")
        
        # -> Fill 'First Name', 'Last Name', and 'Mobile Phone Number' with valid values, then click the 'Submit Order Request' button.
        # 🎂 Submit Order Request button
        elem = page.get_by_role("button", name="🎂 Submit Order Request")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The browser is on the order success page.
        # Assert-outcome: passed
        # Assert: Page URL contains '/order/success'.
        await expect(page).to_have_url(re.compile("/order/success"), timeout=15000), "Page URL contains '/order/success'."
        
        # --> The submitted order is shown with a pending status.
        # Assert-outcome: passed
        # Assert: The page displays the current order status as 'Pending'.
        await expect(page.get_by_role("main").nth(0)).to_contain_text("Current Order Status: Pending", timeout=15000), "The page displays the current order status as 'Pending'."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    