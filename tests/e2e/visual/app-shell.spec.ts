import { test, expect } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';
import { logIn } from './support/auth';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

const MOBILE = { width: 390, height: 844 };

test.beforeEach(async ({ page }) => {
  // The guest footer prints the current year; pin it so the screenshots stay stable.
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

test('guest login', async ({ page }) => {
  await page.goto('/login');
  await expectAppFontLoaded(page);
  await expect(page.getByText('© 2026')).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('guest-login.png', { fullPage: true });
});

test('dashboard desktop', async ({ page }) => {
  await logIn(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('dashboard-desktop.png', { fullPage: true });
});

test('dashboard empty desktop', async ({ page }) => {
  await logIn(page, 'e2e-dashboard-empty-visual@example.com');
  await expect(page.getByRole('heading', { name: 'Zacznij od pierwszego przepisu' })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('dashboard-empty-desktop.png', { fullPage: true });
});

test('dashboard mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('dashboard-mobile.png', { fullPage: true });
});

test('dashboard mobile drawer', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await page.getByRole('button', { name: 'Otwórz nawigację' }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('dashboard-mobile-drawer.png', { fullPage: true });
});
