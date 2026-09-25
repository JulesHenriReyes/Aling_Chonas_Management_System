import asyncio
import re
import time
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

        ts = int(time.time())
        original_email = f"staff_test_{ts}@alingchona.local"
        updated_email = f"staff_updated_{ts}@alingchona.local"

        # -> navigate
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Click 'Staff Login'
        await page.get_by_role("link", name="Staff Login").click()
        
        # -> Login as Owner
        await page.locator("input[name=\"email\"]").fill("owner@alingchona.local")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"))
        
        # -> Open User management
        await page.get_by_role("link", name="Users (Owner)").click()
        await expect(page).to_have_url(re.compile(r"/users"))
        
        # -> Create an isolated test user to edit
        await page.get_by_role("link", name="+ Add User").click()
        await page.locator("input[name=\"first_name\"]").fill("InitialName")
        await page.locator("input[name=\"last_name\"]").fill("TestStaff")
        await page.locator("input[name=\"email\"]").fill(original_email)
        await page.locator("select[name=\"role\"]").select_option("assistant")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.locator("input[name=\"password_confirmation\"]").fill("password123")
        await page.get_by_role("button", name="Create User").click()
        await expect(page).to_have_url(re.compile(r"/users"))
        await expect(page.get_by_role("main")).to_contain_text(original_email)
        
        # -> Edit this isolated user
        test_row = page.locator(f"tr:has-text('{original_email}')").first
        await test_row.get_by_role("link", name="Edit").click()
        await expect(page).to_have_url(re.compile(r"/users/\d+/edit"))
        
        # -> Update First Name and Email
        await page.locator("input[name=\"first_name\"]").fill("UpdatedStaffName")
        await page.locator("input[name=\"email\"]").fill(updated_email)
        await page.get_by_role("button", name="Save Changes").click()
        await expect(page).to_have_url(re.compile(r"/users"))
        
        # -> Assertions: Updated staff account is listed in User Management
        await expect(page.get_by_role("main")).to_contain_text("UpdatedStaffName")
        await expect(page.get_by_role("main")).to_contain_text(updated_email)
        print("TC014 passed with full test data isolation!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())