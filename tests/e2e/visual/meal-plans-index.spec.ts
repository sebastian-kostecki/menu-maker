import { test, expect, type Page } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

const MOBILE = { width: 390, height: 844 };

test.beforeEach(async ({ page }) => {
  // Pin the clock so the dialog's default start date (nearest upcoming Monday) is 2026-06-15.
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

async function openMealPlans(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill('e2e-planner-visual@example.com');
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
  await expectAppFontLoaded(page);

  await page.goto('/meal-plans');
  // Header row plus the five seeded plans, including the processing one with the spinner tag.
  await expect(page.getByRole('row')).toHaveCount(6);
  await expect(page.getByRole('row').filter({ hasText: 'Generowanie' })).toBeVisible();
}

test('meal plans index desktop', async ({ page }) => {
  await openMealPlans(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plans-index-desktop.png', { fullPage: true });
});

test('meal plans index mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await openMealPlans(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plans-index-mobile.png', { fullPage: true });
});

test('meal plans index filtered empty', async ({ page }) => {
  await openMealPlans(page);

  await page.getByRole('combobox', { name: 'Status' }).click();
  await page.getByRole('option', { name: 'Oczekuje' }).click();
  await page.waitForURL(/filter(\[|%5B)status(\]|%5D)=pending/);
  await expect(page.getByText('Brak jadłospisów o wybranym statusie')).toBeVisible();

  // Reload so the shot renders from a hard load (client-side navigation jitters the rounded
  // corners by a few pixels) and so the URL, not stored state, is proven to drive the Select.
  await page.reload();
  await expectAppFontLoaded(page);
  await expect(page.getByRole('combobox', { name: 'Status' })).toHaveText('Oczekuje');
  await expect(page.getByText('Brak jadłospisów o wybranym statusie')).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plans-index-filtered-empty.png', { fullPage: true });

  await page.getByRole('button', { name: 'Wyczyść filtry' }).click();
  await expect(page.getByRole('row')).toHaveCount(6);
});

test('meal plans index generate dialog', async ({ page }) => {
  await openMealPlans(page);

  await page.getByRole('button', { name: 'Wygeneruj jadłospis', exact: true }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.locator('#start_date')).toHaveValue('2026-06-15');
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plans-index-generate-dialog.png', { fullPage: true });
});
