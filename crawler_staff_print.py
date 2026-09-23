import asyncio
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
OUT = r"C:\Users\ok\AppData\Local\Temp\opencode\live\staff_print_real.html"

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

        # Staff print page via POST form like production does
        await page.goto(BASE + "print_staff_cards.php", wait_until="domcontentloaded")
        await page.wait_for_timeout(1000)
        opts = {
            "staff_ids": "27056,25032,27000",
            "designation": "All",
            "department": "All",
        }
        await page.eval_on_selector_all("input,textarea,select", "els => els.length")
        # build and submit a form
        await page.evaluate("""(opts) => {
          var f = document.createElement('form');
          f.method = 'POST'; f.action = 'print_staff_cards.php';
          for (var k in opts) {
            var i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=opts[k];
            f.appendChild(i);
          }
          document.body.appendChild(f); f.submit();
        }""", opts)
        await page.wait_for_timeout(6000)
        print("url:", page.url[:140])
        content = await page.content()
        with open(OUT, "w", encoding="utf-8") as f:
            f.write(content)
        print("saved:", OUT, len(content), "bytes")

        await browser.close()

asyncio.run(run())