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
        browser = await pw.chromium.launch(
            headless=True,
            args=["--window-size=1280,720", "--disable-dev-shm-usage"],
        )
        context = await browser.new_context()
        context.set_default_timeout(15000)
        page = await context.new_page()

        # -> Navigate to home
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")

        # -> Staff Login
        await page.get_by_role("link", name="Staff Login").click()
        await page.locator("input[name=\"email\"]").fill("owner@alingchona.local")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"))

        # -> Navigate to Reports
        await page.get_by_role("link", name="Reports").click()
        await expect(page).to_have_url(re.compile(r"/reports"))

        # -> Verify Financial Reports heading and date filter
        await expect(page.get_by_role("heading", name="Financial Reports")).to_be_visible()
        await expect(page.locator("input[name=\"start_date\"]")).to_be_visible()
        await expect(page.locator("input[name=\"end_date\"]")).to_be_visible()
        await expect(page.get_by_role("button", name="Filter Report")).to_be_visible()

        # -> Verify approved core financial metrics
        main_content = page.get_by_role("main")
        await expect(main_content).to_contain_text("Completed Sales")
        await expect(main_content).to_contain_text("Payment Collections")
        await expect(main_content).to_contain_text("Cancellation Income")
        await expect(main_content).to_contain_text("Total Expenses")
        await expect(main_content).to_contain_text("Operational Net Income")
        await expect(main_content).to_contain_text("Expense Breakdown by Category")

        print("TC035: Financial Reports summary verified according to specification.")
        print("TC035 passed successfully!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())