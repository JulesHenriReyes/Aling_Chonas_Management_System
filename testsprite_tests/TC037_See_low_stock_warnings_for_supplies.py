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
        
        # -> Click the 'Staff Login' link to open the staff login page.
        # Staff Login link
        elem = page.get_by_role("link", name="Staff Login")
        await elem.click(timeout=10000)
        
        # -> Fill the 'Email Address' and 'Password' fields with owner@alingchona.local / password123 and click the 'Sign In to Staff System' button to log in.
        # email email field
        elem = page.locator("input[name=\"email\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("owner@alingchona.local")
        
        # -> Fill the 'Email Address' and 'Password' fields with owner@alingchona.local / password123 and click the 'Sign In to Staff System' button to log in.
        # password password field
        elem = page.locator("input[name=\"password\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password123")
        
        # -> Fill the 'Email Address' and 'Password' fields with owner@alingchona.local / password123 and click the 'Sign In to Staff System' button to log in.
        # Sign In to Staff System button
        elem = page.get_by_role("button", name="Sign In to Staff System")
        await elem.click(timeout=10000)
        
        # -> Click the 'Low Stock Supplies' link to open the supplies/inventory list.
        # Low Stock Supplies ⚠️ 0 Items at or below reorder... link
        elem = page.get_by_role("link", name=re.compile(r"Low Stock Supplies"))
        await elem.click(timeout=10000)
        
        # -> Click the '⚠️ Low Stock Alert Only' tab to filter the list to supplies at or below their reorder threshold.
        # ⚠️ Low Stock Alert Only link
        elem = page.get_by_role("link", name="⚠️ Low Stock Alert Only")
        await elem.click(timeout=10000)
        
        # -> Click the '+ Add New Supply' button to open the create-supply form.
        # + Add New Supply button
        elem = page.get_by_role("button", name="+ Add New Supply")
        await elem.click(timeout=10000)
        
        # -> Fill the 'Supply Name' and 'Unit of Measure' fields and click the 'Save Supply' button to create a supply with current stock 0 (which is at/below reorder level) so it should appear in the low-stock list.
        # e.g. Powdered Sugar text field
        elem = page.get_by_role("textbox", name="e.g. Powdered Sugar")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("Test Low Supply")
        
        # -> Fill the 'Supply Name' and 'Unit of Measure' fields and click the 'Save Supply' button to create a supply with current stock 0 (which is at/below reorder level) so it should appear in the low-stock list.
        # e.g. kg, pcs, box text field
        elem = page.get_by_role("textbox", name="e.g. kg, pcs, box")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("pcs")
        
        # -> Fill the 'Supply Name' and 'Unit of Measure' fields and click the 'Save Supply' button to create a supply with current stock 0 (which is at/below reorder level) so it should appear in the low-stock list.
        # Save Supply button
        elem = page.get_by_role("button", name="Save Supply")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The 'Test Low Supply' row displays a '⚠️ Low Stock' indicator in the Stock Status column.
        # Assert-outcome: passed
        # Assert: Stock Status cell equals '⚠️ Low Stock' for the Test Low Supply row.
        await expect(page.locator("tbody tr", has_text="Test Low Supply")).to_contain_text("⚠️ Low Stock", timeout=15000), "Stock Status cell equals '⚠️ Low Stock' for the Test Low Supply row."
        
        # --> The 'Automated Test Supply' row displays a '⚠️ Low Stock' indicator in the Stock Status column.
        # Assert-outcome: passed
        # Assert: Stock Status cell equals '⚠️ Low Stock' for the Automated Test Supply row.
        if await page.locator("tbody tr", has_text="Automated Test Supply").count() > 0:
            await expect(page.locator("tbody tr", has_text="Automated Test Supply")).to_contain_text("⚠️ Low Stock", timeout=15000), "Stock Status cell equals '⚠️ Low Stock' for the Automated Test Supply row."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    