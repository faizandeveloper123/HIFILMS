import asyncio, os
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
SAVE_DIR = r"C:\Users\ok\AppData\Local\Temp\opencode\live"
os.makedirs(SAVE_DIR, exist_ok=True)

PAGES = [
    ("print_landscape_students_cards.php?students=607646052,607646053&class_id=15433&section=All&cell_number=YES&valid=2026-09-23&color=0067d7&DOB=YES", "print_landscape_students_cards.html"),
]

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, channel="chrome",
            args=["--disable-blink-features=AutomationControlled"])
        ctx = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36",
            viewport={"width": 1400, "height": 900},
        )
        page = await ctx.new_page()
        print("Login page...")
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

        for pname, fname in PAGES:
            url = BASE + pname
            try:
                await page.goto(url, wait_until="domcontentloaded")
                await page.wait_for_timeout(2500)
                content = await page.content()
                pth = os.path.join(SAVE_DIR, fname)
                with open(pth, "w", encoding="utf-8") as f:
                    f.write(content)
                print(f"Saved {fname}: {len(content)} bytes | url={page.url[:80]}")
            except Exception as e:
                print(f"ERROR {pname}: {e}")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())