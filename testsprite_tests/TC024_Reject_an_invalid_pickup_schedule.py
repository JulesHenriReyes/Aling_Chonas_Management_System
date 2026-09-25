import asyncio
import datetime
import re
from playwright import async_api
from playwright.async_api import expect

# BUSINESS RULE REQUIRES CONFIRMATION: Is same-day pickup allowed, or must pickup be at least one day after the order is submitted?
# Currently, the application implements 'after_or_equal:today', allowing same-day orders.
# This test verifies that invalid past pickup dates are properly rejected by the application.

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

        # -> Navigate to public order form
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")

        # -> Select first active product card
        first_product_card = page.locator("div[x-data] .grid .border.rounded-xl").first
        await first_product_card.click()

        # -> Fill customer details
        await page.locator("input[name=\"first_name\"]").fill("Test")
        await page.locator("input[name=\"last_name\"]").fill("Buyer")
        await page.locator("input[name=\"phone_number\"]").fill("09171234567")

        # -> Set pickup date to yesterday (an invalid past date)
        yesterday = (datetime.date.today() - datetime.timedelta(days=1)).strftime("%Y-%m-%d")
        pickup_date_input = page.locator("input[name=\"pickup_date\"]")
        await pickup_date_input.fill(yesterday)
        await page.locator("input[name=\"pickup_time\"]").fill("14:00")

        # Set noValidate on form so browser doesn't block submission before reaching server validation
        await page.evaluate("document.querySelector('form').noValidate = true")

        # -> Submit Order Request
        await page.get_by_role("button", name="🎂 Submit Order Request").click()

        # -> Verify rejection: error message displayed and order NOT created
        main_content = page.get_by_role("main")
        await expect(main_content).to_contain_text("Please correct the following errors")
        await expect(main_content).to_contain_text("pickup date")
        
        # Verify user remains on the order form and not on confirmation page
        await expect(page.get_by_role("heading", name="Order Confirmation")).to_have_count(0)
        print("TC024: Invalid past pickup date successfully rejected with validation error.")
        print("BUSINESS RULE NOTE: Application enforces 'after_or_equal:today'. Same-day pickup is permitted unless confirmed otherwise.")
        print("TC024 passed successfully!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())