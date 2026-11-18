import { test, expect } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';
import { logIn } from './support/auth';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

test.beforeEach(async ({ page }) => {
  // Pin the clock so ages stay stable (Anna 38, Jan 41, Zosia 6).
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

test('family members desktop', async ({ page }) => {
  await logIn(page, 'e2e-family-visual@example.com');
  await page.goto('/family-members');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Członkowie rodziny', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('family-members-desktop.png', { fullPage: true });
});

test('family members mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await logIn(page, 'e2e-family-visual@example.com');
  await page.goto('/family-members');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Członkowie rodziny', level: 1 })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('family-members-mobile.png', { fullPage: true });
});

test('family members empty', async ({ page }) => {
  await logIn(page, 'e2e-dashboard-empty-visual@example.com');
  await page.goto('/family-members');
  await expectAppFontLoaded(page);
  await expect(page.getByRole('heading', { name: 'Członkowie rodziny', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Brak członków rodziny' })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('family-members-empty.png', { fullPage: true });
});
