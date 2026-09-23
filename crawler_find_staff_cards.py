import asyncio, re
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"

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

        # Grab the dashboard and list links mentioning staff
        await page.goto(BASE + "dashboard_new.php", wait_until="domcontentloaded")
        await page.wait_for_timeout(2000)
        links = await page.eval_on_selector_all("a", "els => els.map(e => ({t:(e.innerText||'').trim(), h:(e.getAttribute('href')||'')}))")
        seen = set()
        for l in links:
            key = (l["t"].lower(), l["h"])
            if key in seen: continue
            seen.add(key)
            if "staff" in l["t"].lower() or "staff" in l["h"].lower() or "employee" in l["t"].lower():
                print(repr(l))
        await browser.close()

asyncio.run(run())