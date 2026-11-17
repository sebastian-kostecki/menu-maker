import { test, expect } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';
import { logIn } from './support/auth';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

test.beforeEach(async ({ page }) => {
  // The guest footer prints the current year; pin it so the screenshots stay stable.
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

test('guest register', async ({ page }) => {
  await page.goto('/register');
  await expectAppFontLoaded(page);
  await expect(page.getByText('© 2026')).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('guest-register.png', { fullPage: true });
});

test('profile', async ({ page }) => {
  await logIn(page);
  await page.goto('/profile');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Profil', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('profile-desktop.png', { fullPage: true });
});
