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
        
        # --> Assertions to verify final state
        elem = page.locator("text=Classic Chocolate Dream Cake").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Classic Chocolate Dream Cake product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Classic Chocolate Dream Cake' to be visible on the public order page"
        price = page.locator("text=Starting at ₱800.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱800.00" is shown for Classic Chocolate Dream Cake
        assert 'Starting at ₱800.00' in text, 'Expected price "Starting at ₱800.00" to be visible for Classic Chocolate Dream Cake'
        cb = page.locator('input[type="checkbox"]').nth(0)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Classic Chocolate Dream Cake is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Classic Chocolate Dream Cake to be enabled on the public order page"
        elem = page.locator("text=Custom Buttercream Birthday Cake").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Custom Buttercream Birthday Cake product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Custom Buttercream Birthday Cake' to be visible on the public order page"
        price = page.locator("text=Starting at ₱950.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱950.00" is shown for Custom Buttercream Birthday Cake
        assert 'Starting at ₱950.00' in text, 'Expected price "Starting at ₱950.00" to be visible for Custom Buttercream Birthday Cake'
        cb = page.locator('input[type="checkbox"]').nth(1)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Custom Buttercream Birthday Cake is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Custom Buttercream Birthday Cake to be enabled on the public order page"
        elem = page.locator("text=Custom Fondant Celebration Cake").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Custom Fondant Celebration Cake product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Custom Fondant Celebration Cake' to be visible on the public order page"
        price = page.locator("text=Starting at ₱1,500.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱1,500.00" is shown for Custom Fondant Celebration Cake
        assert 'Starting at ₱1,500.00' in text, 'Expected price "Starting at ₱1,500.00" to be visible for Custom Fondant Celebration Cake'
        cb = page.locator('input[type="checkbox"]').nth(2)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Custom Fondant Celebration Cake is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Custom Fondant Celebration Cake to be enabled on the public order page"
        elem = page.locator("text=Custom Party Cupcakes (Box of 12)").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Custom Party Cupcakes (Box of 12) product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Custom Party Cupcakes (Box of 12)' to be visible on the public order page"
        price = page.locator("text=Starting at ₱480.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱480.00" is shown for Custom Party Cupcakes (Box of 12)
        assert 'Starting at ₱480.00' in text, 'Expected price "Starting at ₱480.00" to be visible for Custom Party Cupcakes (Box of 12)'
        cb = page.locator('input[type="checkbox"]').nth(3)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Custom Party Cupcakes (Box of 12) is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Custom Party Cupcakes (Box of 12) to be enabled on the public order page"
        elem = page.locator("text=Custom Party Cupcakes (Box of 6)").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Custom Party Cupcakes (Box of 6) product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Custom Party Cupcakes (Box of 6)' to be visible on the public order page"
        price = page.locator("text=Starting at ₱250.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱250.00" is shown for Custom Party Cupcakes (Box of 6)
        assert 'Starting at ₱250.00' in text, 'Expected price "Starting at ₱250.00" to be visible for Custom Party Cupcakes (Box of 6)'
        cb = page.locator('input[type="checkbox"]').nth(4)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Custom Party Cupcakes (Box of 6) is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Custom Party Cupcakes (Box of 6) to be enabled on the public order page"
        elem = page.locator("text=Moist Vanilla Celebration Cake").nth(0)
        await elem.scroll_into_view_if_needed()
        # Assert: Moist Vanilla Celebration Cake product card is visible on the public order page
        assert await elem.is_visible(), "Expected product 'Moist Vanilla Celebration Cake' to be visible on the public order page"
        price = page.locator("text=Starting at ₱750.00").nth(0)
        await price.scroll_into_view_if_needed()
        text = await price.text_content()
        # Assert: Product price "Starting at ₱750.00" is shown for Moist Vanilla Celebration Cake
        assert 'Starting at ₱750.00' in text, 'Expected price "Starting at ₱750.00" to be visible for Moist Vanilla Celebration Cake'
        cb = page.locator('input[type="checkbox"]').nth(5)
        await cb.scroll_into_view_if_needed()
        enabled = await cb.is_enabled()
        # Assert: Selection checkbox for Moist Vanilla Celebration Cake is enabled on the public order page
        assert enabled, "Expected the selection checkbox for Moist Vanilla Celebration Cake to be enabled on the public order page"
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    