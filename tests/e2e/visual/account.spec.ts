import { test, expect } from '@playwright/test';
import { expectFigtreeLoaded } from './support/fonts';

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
  await expectFigtreeLoaded(page);
  await expect(page.getByText('© 2026')).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('guest-register.png', { fullPage: true });
});
