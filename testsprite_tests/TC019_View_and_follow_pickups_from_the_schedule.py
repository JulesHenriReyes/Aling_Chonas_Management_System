import asyncio
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

        # -> navigate
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Log in as staff
        await page.get_by_role("link", name="Staff Login").click()
        await page.locator("input[name=\"email\"]").fill("owner@alingchona.local")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"))
        
        # -> Open Pickup Schedule
        await page.get_by_role("link", name="Pickup Schedule").click()
        await expect(page).to_have_url(re.compile(r"/pickup-schedule"))
        
        # -> Verify Pickup Schedule heading and date filter
        await expect(page.get_by_role("heading", name="Pickup Schedule")).to_be_visible()
        await expect(page.locator("input[name=\"pickup_date\"]")).to_be_visible()
        await expect(page.get_by_role("button", name="Filter Date")).to_be_visible()
        
        # -> Verify chronological orders or active pickups list
        # If orders are present, check the first pickup card details
        view_links = page.get_by_role("link", name="View")
        if await view_links.count() > 0:
            # Click view to inspect order details
            await view_links.first.click()
            await expect(page.locator("h1")).to_contain_text("ORD-")
            await expect(page.get_by_role("main")).to_contain_text("Customer Information")
            print("  Opened order details from pickup schedule successfully.")
        else:
            await expect(page.get_by_role("main")).to_contain_text("No active pickups scheduled")
            print("  Schedule rendered empty state cleanly.")
        
        print("TC019 passed successfully!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())