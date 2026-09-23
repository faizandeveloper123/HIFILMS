import asyncio, os
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
OUT = r"C:\Users\ok\AppData\Local\Temp\opencode\live\landscape_cards_real.html"

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, channel="chrome",
            args=["--disable-blink-features=AutomationControlled"])
        ctx = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36",
            viewport={"width": 1400, "height": 900},
        )
        page = await ctx.new_page()
        await page.goto(BASE, wait_until="domcontentloaded")
        await page.wait_for_timeout(1500)
        await page.locator("input#email").fill("hiraanwar@gmail.com")
        await page.locator("input#password").fill("hira1475434")
        await page.locator("button.btn-login").click()
        await page.wait_for_timeout(4000)
        cur = page.url
        print("After login url:", cur)
        if "index.php" in cur and "clerror" in cur:
            print("!! LOGIN FAILED")
            await browser.close()
            return

        # Open Student Cards page
        await page.goto(BASE + "students_card.php", wait_until="domcontentloaded")
        await page.wait_for_timeout(3000)
        print("card gen page loaded, title:", await page.title())

        # Tick first 3 student checkboxes and trigger a change to update count
        boxes = page.locator('input[name="students[]"]')
        n = await boxes.count()
        print("student checkboxes found:", n)
        if n == 0:
            print("no checkboxes; dump page for debug")
            await page.goto(BASE + "students_card.php", wait_until="load")
            await page.wait_for_timeout(3000)
            with open(r"C:\Users\ok\AppData\Local\Temp\opencode\live\students_card_debug.html", "w", encoding="utf-8") as f:
                f.write(await page.content())
            await browser.close()
            return
        for i in range(min(3, n)):
            await boxes.nth(i).check()

        # Trigger the landscape tile function directly (its own page settings render default values)
        await page.evaluate("classicLandscapeCards()")
        await page.wait_for_timeout(4000)
        print("landscape page url:", page.url[:120])
        content = await page.content()
        with open(OUT, "w", encoding="utf-8") as f:
            f.write(content)
        print("Saved landscape_cards_real.html:", len(content), "bytes")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())