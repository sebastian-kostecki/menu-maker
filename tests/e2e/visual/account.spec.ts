import { test, expect } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';
import { logIn } from './support/auth';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

test.beforeEach(async ({ page }) => {
  // Pin the clock so date-dependent content stays stable.
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

test('guest register', async ({ page }) => {
  await page.goto('/register');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Rejestracja', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('guest-register.png', { fullPage: true });
});

test('guest forgot password', async ({ page }) => {
  await page.goto('/forgot-password');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Nie pamiętasz hasła?', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('guest-forgot-password.png', { fullPage: true });
});

test('profile', async ({ page }) => {
  await logIn(page);
  await page.goto('/profile');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Profil', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('profile-desktop.png', { fullPage: true });
});

test('profile mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await logIn(page);
  await page.goto('/profile');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Profil', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('profile-mobile.png', { fullPage: true });
});
