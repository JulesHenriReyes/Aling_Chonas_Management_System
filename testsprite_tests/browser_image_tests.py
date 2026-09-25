import asyncio
import os
import re
from playwright.async_api import async_playwright, expect

async def run_image_e2e_tests():
    fixture_path = os.path.abspath("testsprite_tests/fixtures/sample_cake.png")
    assert os.path.exists(fixture_path), f"Fixture not found at {fixture_path}"

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context()
        page = await context.new_page()

        print("--- Test 1: Public Reference Image Upload ---")
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")

        # Select product card via unique heading
        await page.get_by_role("heading", name="Classic Chocolate Dream Cake").click()

        # Fill customer information
        await page.locator('input[name="first_name"]').fill("Maria")
        await page.locator('input[name="last_name"]').fill("Santos")
        await page.locator('input[name="phone_number"]').fill("09171234567")

        # Upload general reference image
        general_upload = page.locator('input[name="images[]"]')
        await general_upload.set_input_files(fixture_path)
        print("  Attached general reference photo:", fixture_path)

        # Submit form
        await page.get_by_role("button", name="🎂 Submit Order Request").click()
        await expect(page).to_have_url(re.compile(r"/order/success"), timeout=15000)
        await expect(page.get_by_role("main")).to_contain_text("Current Order Status: Pending")
        
        order_ref_elem = page.locator("text=Order Reference:")
        await expect(order_ref_elem).to_be_visible(timeout=15000)
        order_ref_text = await order_ref_elem.inner_text()
        order_number = order_ref_text.replace("Order Reference:", "").strip()
        print(f"  Public order with general image submitted successfully: {order_number}")

        print("--- Test 2: Public Per-Product Image Association ---")
        await page.goto("http://localhost:8000/")
        await page.wait_for_load_state("domcontentloaded")

        # Select product
        await page.get_by_role("heading", name="Custom Fondant Celebration Cake").click()

        # Fill per-product image
        per_item_upload = page.locator('input[type="file"][name*="[images][]"]').first
        await per_item_upload.set_input_files(fixture_path)
        print("  Attached per-item reference photo:", fixture_path)

        # Fill customer info
        await page.locator('input[name="first_name"]').fill("Juan")
        await page.locator('input[name="last_name"]').fill("Dela Cruz")
        await page.locator('input[name="phone_number"]').fill("09187654321")

        # Submit form
        await page.get_by_role("button", name="🎂 Submit Order Request").click()
        await expect(page).to_have_url(re.compile(r"/order/success"), timeout=15000)
        per_item_ref = await page.locator("text=Order Reference:").inner_text()
        print(f"  Public order with per-product image submitted successfully: {per_item_ref.strip()}")

        print("--- Test 3: Staff Image Upload & Image Rendering from Storage ---")
        # Log in as Assistant
        await page.goto("http://localhost:8000/login")
        await page.locator('input[name="email"]').fill("assistant@alingchona.local")
        await page.locator('input[name="password"]').fill("password123")
        await page.get_by_role("button", name="Sign In to Staff System").click()
        await expect(page).to_have_url(re.compile(r"/dashboard"), timeout=15000)
        print("  Logged in as Assistant successfully.")

        # Navigate to orders
        await page.get_by_role("link", name="Orders", exact=True).click()
        await expect(page).to_have_url(re.compile(r"/orders"), timeout=15000)

        # Open the specific order submitted in Test 1
        await page.get_by_role("link", name=order_number).first.click()
        await expect(page).to_have_url(re.compile(r"/orders/\d+"), timeout=15000)
        print(f"  Opened order details view for {order_number}.")

        # Verify reference image renders in browser
        images = page.locator('img[alt="sample_cake.png"]')
        await expect(images.first).to_be_visible(timeout=15000)
        image_src = await images.first.get_attribute("src")
        print(f"  Image element rendered with src: {image_src}")

        # Verify image URL returns HTTP 200 from storage
        resp = await page.request.get(image_src)
        assert resp.status == 200, f"Image failed to load: status {resp.status}"
        assert resp.headers.get("content-type", "").startswith("image/"), "Content-type is not image"
        print("  Storage response verified: HTTP 200 with image MIME type.")

        # Staff uploads additional reference image
        staff_upload = page.locator('form[action*="/images"] input[name="image"]')
        await staff_upload.set_input_files(fixture_path)
        await page.get_by_role("button", name="Upload").click()
        await page.wait_for_load_state("networkidle")
        print("  Staff uploaded additional reference image.")

        # Verify staff image appears in the gallery
        await expect(page.locator('img[alt="sample_cake.png"]')).to_have_count(2, timeout=15000)
        print("  Both customer peg and staff upload render simultaneously in order gallery.")

        await context.close()
        await browser.close()
        print("--- ALL IMAGE TESTS PASSED SUCCESSFULLY! ---")

if __name__ == "__main__":
    asyncio.run(run_image_e2e_tests())
