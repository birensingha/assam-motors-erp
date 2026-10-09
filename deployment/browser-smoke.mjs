import { chromium } from 'playwright';
import fs from 'node:fs';

const base = (process.env.STAGING_BASE_URL || 'https://staging.assammotors.com').replace(/\/$/, '');
const loginPath = process.env.STAGING_LOGIN_PATH || '/erp/login';
const user = process.env.STAGING_ADMIN_LOGIN || '';
const password = process.env.STAGING_ADMIN_PASSWORD || '';
const out = process.env.SMOKE_OUTPUT_DIR || 'browser-smoke-artifacts';

fs.mkdirSync(out, { recursive: true });

const targets = [
  ['/erp/job-cards', 'job-cards'],
  ['/erp/purchases/osl/create', 'osl-purchase'],
  ['/erp/customers', 'customers'],
  ['/legacy/workshop/customers.php', 'legacy-customers']
];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const failures = [];

async function bodyText() {
  return await page.locator('body').innerText().catch(() => '');
}

async function checkPage(path, label) {
  const response = await page.goto(base + path, { waitUntil: 'networkidle', timeout: 45000 });
  const status = response?.status() || 0;
  const body = await bodyText();

  await page.screenshot({ path: out + '/' + label + '.png', fullPage: true });

  if (status >= 500) failures.push(label + ': HTTP ' + status);
  if (/SQLSTATE|Fatal error|Parse error|Internal Server Error|Whoops/i.test(body)) {
    failures.push(label + ': server error text visible');
  }
  if (/Admin login required/i.test(body) && label === 'legacy-customers') {
    failures.push(label + ': legacy admin-login error still visible');
  }
}

try {
  if (!user || !password) {
    throw new Error('Missing STAGING_ADMIN_LOGIN or STAGING_ADMIN_PASSWORD');
  }

  await page.goto(base + loginPath, { waitUntil: 'domcontentloaded', timeout: 45000 });

  const email = page.locator('input[type="email"], input[name="email"], input[name="username"]').first();
  const pass = page.locator('input[type="password"]').first();

  if (await email.count() === 0 || await pass.count() === 0) {
    throw new Error('Unable to identify staging login fields');
  }

  await email.fill(user);
  await pass.fill(password);

  const submit = page.locator('button[type="submit"], input[type="submit"]').first();
  if (await submit.count() === 0) throw new Error('Unable to identify staging login submit button');

  await Promise.all([
    page.waitForLoadState('networkidle').catch(() => {}),
    submit.click()
  ]);

  const loggedBody = await bodyText();
  if (/invalid|incorrect|failed|login required/i.test(loggedBody) && page.url().includes('login')) {
    throw new Error('Staging admin login failed');
  }

  await page.screenshot({ path: out + '/after-login.png', fullPage: true });

  for (const [path, label] of targets) {
    await checkPage(path, label);
  }
} catch (error) {
  failures.push(String(error?.message || error));
  await page.screenshot({ path: out + '/failure.png', fullPage: true }).catch(() => {});
}

fs.writeFileSync(out + '/result.json', JSON.stringify({
  checked_at: new Date().toISOString(),
  base_url: base,
  failures
}, null, 2));

await browser.close();

if (failures.length) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Browser smoke: PASS');
