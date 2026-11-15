import { test, expect, type Page } from '@playwright/test';
import { expectFigtreeLoaded } from './support/fonts';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

const DESKTOP = { width: 1280, height: 900 };
const MOBILE = { width: 390, height: 844 };

test.beforeEach(async ({ page }) => {
  // Same fixed clock as the other visual specs; the seeded plan starts on 2026-06-15.
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

async function logIn(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill('e2e-planner-visual@example.com');
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
  await expectFigtreeLoaded(page);
}

// Read the plan URL from the Index's Inertia props, so the spec does not depend on the Index UI.
async function openSeededPlan(page: Page) {
  await page.goto('/meal-plans');
  const pageJson = await page.locator('script[data-page="app"]').textContent();
  expect(pageJson, 'Inertia page JSON in script[data-page="app"]').not.toBeNull();
  const url: string = JSON.parse(pageJson!).props.mealPlans.data[0].links.self;
  await page.goto(url);

  await expect(page.getByRole('heading', { level: 1, name: 'Jadłospis' })).toBeVisible();
  await expect(page.getByText('15.06.2026 – 21.06.2026')).toBeVisible();
  await expect(page.getByTestId('meal-plan-grid').locator('tbody td a')).toHaveCount(21);
  await expectFigtreeLoaded(page);
}

test('meal plan show desktop', async ({ page }) => {
  await page.setViewportSize(DESKTOP);
  await logIn(page);
  await openSeededPlan(page);

  const trigger = page.getByTestId('meal-plan-grid').getByRole('button', { name: /^Składniki/ }).first();
  await trigger.click();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');

  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plan-show-desktop.png', { fullPage: true });
});

test('meal plan show mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await openSeededPlan(page);

  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plan-show-mobile.png', { fullPage: true });
});
