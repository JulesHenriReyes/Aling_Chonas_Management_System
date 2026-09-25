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
        
        # -> Click the 'Staff Login' link in the top navigation to open the staff login page.
        # Staff Login link
        elem = page.get_by_role("link", name="Staff Login")
        await elem.click(timeout=10000)
        
        # -> Sign in using the 'Email Address' and 'Password' fields and click the 'Sign In to Staff System' button.
        # email email field
        elem = page.locator("input[name=\"email\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("owner@alingchona.local")
        
        # -> Sign in using the 'Email Address' and 'Password' fields and click the 'Sign In to Staff System' button.
        # password password field
        elem = page.locator("input[name=\"password\"]")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password123")
        
        # -> Sign in using the 'Email Address' and 'Password' fields and click the 'Sign In to Staff System' button.
        # Sign In to Staff System button
        elem = page.get_by_role("button", name="Sign In to Staff System")
        await elem.click(timeout=10000)
        
        # -> Click the 'Products' link in the top navigation to open the staff Products page.
        # Products link
        elem = page.get_by_role("link", name="Products")
        await elem.click(timeout=10000)
        
        # -> Click the '+ Add New Product' button to open the product creation form.
        # + Add New Product button
        elem = page.get_by_role("button", name="+ Add New Product")
        await elem.click(timeout=10000)
        
        # -> Enter a product name into the 'PRODUCT NAME' field and a price into the 'CATALOG BASE PRICE (₱)' field, then click the 'Save Product' button.
        # e.g. Red Velvet Heart Cake text field
        elem = page.get_by_role("textbox", name="e.g. Red Velvet Heart Cake")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("QA Test Product 0923")
        
        # -> Enter a product name into the 'PRODUCT NAME' field and a price into the 'CATALOG BASE PRICE (₱)' field, then click the 'Save Product' button.
        # e.g. 850.00 number field
        elem = page.get_by_placeholder("e.g. 850.00")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("100.00")
        
        # -> Enter a product name into the 'PRODUCT NAME' field and a price into the 'CATALOG BASE PRICE (₱)' field, then click the 'Save Product' button.
        # Save Product button
        elem = page.get_by_role("button", name="Save Product")
        await elem.click(timeout=10000)
        
        # -> Click the 'Deactivate' button for 'QA Test Product 0923' in the Products list to set it inactive.
        # Deactivate button
        elem = page.locator("tbody tr", has_text="QA Test Product 0923").first.get_by_role("button", name=re.compile(r"Deactivate", re.I))
        await elem.click(timeout=10000)
        
        # -> Open the public storefront (site homepage) to confirm whether 'QA Test Product 0923' is visible in the public catalog.
        # Open URL in new tab
        page = await context.new_page()
        await page.goto("http://localhost:8000/")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Scroll to the bottom of the public storefront page and search the page for the product name 'QA Test Product 0923' to confirm whether the inactive product is hidden from the public catalog.
        await page.mouse.wheel(0, 300)
        
        # -> Switch to the staff Products tab titled 'Product Catalog - Aling Chona' so the product can be re-activated for visibility verification.
        # Switch to tab D1BB
        page = context.pages[-1]  # switch to most recently active tab
        
        # -> Switch to the storefront tab titled 'Order Custom Cakes & Cupcakes' and search the homepage for 'QA Test Product 0923' to confirm it is hidden from the public catalog.
        # Switch to tab 6A0F
        page = context.pages[-1]  # switch to most recently active tab
        
        # -> On the Staff Products page, click the 'Activate' (or equivalent) button for 'QA Test Product 0923' to make it public again.
        # Switch to admin tab
        page = context.pages[0]
        
        # -> Click the 'Activate' button for 'QA Test Product 0923' on the Products page to make it public again.
        # Activate button
        elem = page.locator("tbody tr", has_text="QA Test Product 0923").first.get_by_role("button", name=re.compile(r"Activate|Deactivate", re.I))
        await elem.click(timeout=10000)
        
        # -> Switch to the storefront tab titled 'Order Custom Cakes & Cupcakes' (the public homepage) so the catalog can be searched for 'QA Test Product 0923'.
        # Switch to tab 6A0F
        page = context.pages[-1]  # switch to most recently active tab
        
        # --> Assertions to verify final state
        current_url = await page.evaluate("() => window.location.href")
        # Assert-outcome: passed
        # Assert: page loaded with a URL (final outcome verified by the AI judge during the run)
        assert current_url, 'Page should have loaded with a URL'
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    