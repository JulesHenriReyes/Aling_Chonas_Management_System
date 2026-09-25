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

        unique_assistant_email = f"assistant_{int(time.time())}@alingchona.local"

        # -> navigate
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Click the 'Staff Login' link to open the admin login page.
        await page.get_by_role("link", name="Staff Login").click()
        
        # -> Log in as Owner
        await page.locator("input[name=\"email\"]").fill("owner@alingchona.local")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"))
        
        # -> Click 'Users (Owner)' link in top navigation
        await page.get_by_role("link", name="Users (Owner)").click()
        await expect(page).to_have_url(re.compile(r"/users"))
        
        # -> Click the '+ Add User' button
        await page.get_by_role("link", name="+ Add User").click()
        await expect(page).to_have_url(re.compile(r"/users/create"))
        
        # -> Fill the 'Create Internal User' form with unique test assistant
        await page.locator("input[name=\"first_name\"]").fill("Dedicated")
        await page.locator("input[name=\"last_name\"]").fill("Assistant")
        await page.locator("input[name=\"email\"]").fill(unique_assistant_email)
        await page.locator("select[name=\"role\"]").select_option("assistant")
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.locator("input[name=\"password_confirmation\"]").fill("password123")
        
        # -> Submit user form
        await page.get_by_role("button", name="Create User").click()
        await expect(page).to_have_url(re.compile(r"/users"))
        await expect(page.get_by_role("main")).to_contain_text(unique_assistant_email)
        print(f"  Owner created dedicated test assistant: {unique_assistant_email}")
        
        # -> Click 'Sign Out'
        await page.get_by_role("button", name="Sign Out").click()
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Navigate to login
        await page.goto("http://localhost:8000/login")
        await page.wait_for_load_state("domcontentloaded")
        
        # -> Log in with the newly created dedicated Assistant credentials
        await page.locator("input[name=\"email\"]").fill(unique_assistant_email)
        await page.locator("input[name=\"password\"]").fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"))
        print("  Logged in as dedicated Assistant successfully.")
        
        # -> Verify Assistant can access normal admin areas
        await expect(page.get_by_role("link", name="Orders", exact=True)).to_be_visible()
        await expect(page.get_by_role("link", name="Products", exact=True)).to_be_visible()
        
        # -> Verify 'Users (Owner)' link is NOT in navigation for Assistant
        await expect(page.get_by_role("link", name="Users (Owner)")).to_have_count(0)
        
        # -> Attempt to directly open /users and verify 403 Forbidden is returned
        response = await page.goto("http://localhost:8000/users")
        assert response.status == 403, f"Expected HTTP 403, got {response.status}"
        print("  Direct access to /users correctly rejected with HTTP 403 Forbidden.")
        
        print("TC012 passed successfully!")

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

if __name__ == "__main__":
    asyncio.run(run_test())