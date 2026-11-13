// Usage: node capture.mjs <before|after>  (not part of the test suite)
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const label = process.argv[2] ?? 'before';
const base = process.env.E2E_BASE_URL ?? 'http://localhost:8081';
const out = new URL(`./${label}/`, import.meta.url).pathname;
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'pl-PL' });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

async function shot(name, url) {
  if (url) await page.goto(base + url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  for (const mode of ['light', 'dark']) {
    await page.evaluate((m) => document.documentElement.classList.toggle('dark', m === 'dark'), mode);
    await page.waitForTimeout(150);
    await page.screenshot({ path: `${out}${name}.${mode}.png`, fullPage: true });
  }
  await page.evaluate(() => document.documentElement.classList.remove('dark'));
}

await shot('welcome', '/');
await shot('login', '/login');
await shot('register', '/register');
await shot('forgot-password', '/forgot-password');

await page.goto(base + '/login');
await page.getByLabel('Adres e-mail').fill('shot@example.com');
await page.locator('input[type=password]').fill('Password123!');
await page.locator('input[type=password]').press('Enter');
await page.waitForURL('**/dashboard');

await shot('dashboard', '/dashboard');
await shot('recipes-index', '/recipes');
await shot('recipes-create', '/recipes/create');
const rid = process.env.RECIPE_ID ?? '1';
await shot('recipes-show', `/recipes/${rid}`);
await shot('recipes-edit', `/recipes/${rid}/edit`);
await shot('family-members', '/family-members');
await shot('family-members-create', '/family-members/create');
await shot('meal-plans', '/meal-plans');
await shot('profile', '/profile');

// dialog / dropdown states
await page.goto(base + '/family-members', { waitUntil: 'networkidle' });
const btn = page.getByRole('button', { name: /^Usuń/ }).first();
if (await btn.count()) { await btn.click(); await shot('family-members-dialog'); await page.keyboard.press('Escape'); }

console.log('console/page errors:', errors.length ? errors : 'none');
await browser.close();
