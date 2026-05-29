import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'spa-snapshot');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();

// Try several possible hash forms to find the right one.
const urls = [
  'https://demo.strichliste.org/#!/user/1177/send_money_to_a_friend',
  'https://demo.strichliste.org/#/user/1177/send_money_to_a_friend',
];

let landed = null;
for (const url of urls) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(2000);
  const finalUrl = page.url();
  const hasSendMoney =
    finalUrl.includes('send_money_to_a_friend') ||
    (await page.locator('text=/send.?money/i').count()) > 0;
  if (hasSendMoney) {
    landed = finalUrl;
    break;
  }
}

if (!landed) {
  console.error('could not navigate to send-money page; falling back to user detail and clicking SEND MONEY');
  await page.goto('https://demo.strichliste.org/#/user/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  const sendMoneyLink = page.getByText(/send money/i).first();
  if (await sendMoneyLink.count()) {
    await sendMoneyLink.click();
    await page.waitForTimeout(1500);
    landed = page.url();
  }
}

console.log('landed on', landed);

await page.screenshot({ path: path.join(outDir, 'send-money.png'), fullPage: true });
const html = await page.content();
fs.writeFileSync(path.join(outDir, 'send-money.html'), html);

const computed = await page.evaluate(() => {
  const props = ['display', 'padding', 'margin', 'background-color', 'box-shadow',
                 'font-size', 'font-weight', 'text-transform', 'border-radius',
                 'grid-template-columns', 'grid-template-rows', 'gap', 'border',
                 'width', 'height', 'position', 'justify-content', 'align-items',
                 'flex-direction', 'color', 'border-bottom', 'border-top', 'cursor'];
  const selectors = [
    'main',
    '[class*="ArticleListItem"]',
    '[class*="SendMoney"]', '[class*="sendMoney"]', '[class*="send-money"]',
    '[class*="UserToUser"]', '[class*="UserSelection"]',
    '[class*="user-selection"]', '[class*="userSelection"]',
    '[class*="SearchInput"]', '[class*="SearchList"]',
    '[class*="ScrollContainer"]', '[class*="scroll-container"]',
    '[class*="UserCard"]', '[class*="UserName"]',
    '[class*="CustomAmount"]', '[class*="customAmount"]',
    '[class*="PaymentButton"]', '[class*="paymentButton"]',
    '[class*="StepButton"]', '[class*="stepButton"]',
    '[class*="Button"]:not([class*="cancel"])', '[class*="cancelButton"]',
    '[class*="userTransactionGrid"]', '[class*="UserTransactionGrid"]',
    'input', 'select', 'button',
  ];
  const out = {};
  for (const sel of selectors) {
    const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 5);
    out[sel] = nodes.map((n) => {
      const cs = getComputedStyle(n);
      const row = { tag: n.tagName.toLowerCase(), classes: n.className?.toString?.() ?? '' };
      for (const p of props) row[p] = cs.getPropertyValue(p);
      return row;
    });
  }
  return out;
});
fs.writeFileSync(path.join(outDir, 'send-money.computed.json'), JSON.stringify(computed, null, 2));

// Dump the body HTML tree (after the SPA hydration) for human inspection.
const bodyHtml = await page.evaluate(() => document.body.outerHTML);
fs.writeFileSync(path.join(outDir, 'send-money.body.html'), bodyHtml);

await browser.close();
console.log('wrote', outDir, '/send-money.{png,html,body.html,computed.json}');
