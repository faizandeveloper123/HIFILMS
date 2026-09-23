import asyncio
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
OUT = r"C:\Users\ok\AppData\Local\Temp\opencode\live\staff_cards_real.html"

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, channel="chrome",
            args=["--disable-blink-features=AutomationControlled"])
        ctx = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36",
            viewport={"width": 1500, "height": 950},
        )
        page = await ctx.new_page()
        await page.goto(BASE, wait_until="domcontentloaded")
        await page.wait_for_timeout(1500)
        await page.locator("input#email").fill("hiraanwar@gmail.com")
        await page.locator("input#password").fill("hira1475434")
        await page.locator("button.btn-login").click()
        await page.wait_for_timeout(4000)
        if "clerror" in page.url:
            print("!! LOGIN FAILED")
            await browser.close()
            return

        await page.goto(BASE + "cards.php", wait_until="domcontentloaded")
        await page.wait_for_timeout(3000)
        print("title:", await page.title())
        content = await page.content()
        with open(OUT, "w", encoding="utf-8") as f:
            f.write(content)
        print("saved:", OUT, len(content), "bytes")

        # inspect script/page for print targets
        html = content
        import re
        for m in re.finditer(r'print_[a-z_]+\.php[^\s"\'&]*', html):
            print("PRINT REF:", m.group(0)[:110])
        await browser.close()

asyncio.run(run())