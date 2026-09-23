import asyncio
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
OUTS = {
    "landscape": r"C:\Users\ok\AppData\Local\Temp\opencode\live\backside_landscape_real.html",
    "portrait": r"C:\Users\ok\AppData\Local\Temp\opencode\live\backside_portrait_real.html",
}

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
        if "clerror" in page.url:
            print("!! LOGIN FAILED")
            await browser.close()
            return

        for name, out in OUTS.items():
            f = "print_landscape_backside_stdcard.php" if name == "landscape" else "print_portrait_backside_stdcard.php"
            url = (BASE + f + "?valid=2026-09-23&color=9c27b0&num=8"
                   + "&note=" + __import__("urllib.parse", fromlist=["quote"]).quote("In case of loss, kindly return this card to the school office."))
            await page.goto(url, wait_until="domcontentloaded")
            await page.wait_for_timeout(4000)
            content = await page.content()
            with open(out, "w", encoding="utf-8") as fh:
                fh.write(content)
            print(name, "->", out, len(content), "bytes", page.url[:120])

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())