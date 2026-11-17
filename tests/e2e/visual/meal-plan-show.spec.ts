import { test, expect, type Page } from '@playwright/test';
import { expectFigtreeLoaded } from './support/fonts';
import { logIn } from './support/auth';

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

// Read the plan URL from the Index's Inertia props, so the spec does not depend on the Index UI.
async function openSeededPlan(page: Page) {
  await page.goto('/meal-plans');
  const pageJson = await page.locator('script[data-page="app"]').textContent();
  expect(pageJson, 'Inertia page JSON in script[data-page="app"]').not.toBeNull();
  // The visual user also owns the plans-list fixtures; pick the deterministic plan by its start date.
  const plans: { start_date: string; links: { self: string } }[] = JSON.parse(pageJson!).props.mealPlans.data;
  const plan = plans.find((candidate) => candidate.start_date === '2026-06-15');
  expect(plan, 'seeded plan starting 2026-06-15 in the plans list').toBeDefined();
  const url = plan!.links.self;
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

// The pinned draft starts on 2026-06-29; apart from Tuesday's Śniadanie (see below) it uses the visual user's recipes, so the swap dialog marks each non-current one "jest już".
// Monday's Kolacja also feeds Tuesday's Śniadanie: Tuesday's Śniadanie now holds Monday's Kolacja, "Kanapki z pastą jajeczną".
// Monday's Obiad feeds Tuesday's: the link turns Tuesday's "Kotlety mielone z ziemniakami" into Monday's "Rosół z makaronem", so Kotlety is no longer "jest już" in the Obiad dialog.
async function openSeededDraft(page: Page) {
  await page.goto('/meal-plans');
  const pageJson = await page.locator('script[data-page="app"]').textContent();
  expect(pageJson, 'Inertia page JSON in script[data-page="app"]').not.toBeNull();
  const plans: { start_date: string; links: { self: string } }[] = JSON.parse(pageJson!).props.mealPlans.data;
  const plan = plans.find((candidate) => candidate.start_date === '2026-06-29');
  expect(plan, 'seeded draft starting 2026-06-29 in the plans list').toBeDefined();
  await page.goto(plan!.links.self);

  await expect(page.getByRole('heading', { level: 1, name: 'Jadłospis' })).toBeVisible();
  await expect(page.getByText('29.06.2026 – 05.07.2026')).toBeVisible();
  // One pinned empty meal (Wednesday breakfast): 20 recipe links.
  await expect(page.getByTestId('meal-plan-grid').locator('tbody td a')).toHaveCount(20);
  await expect(page.getByText('Bez posiłku').first()).toBeVisible();
  await expect(page.getByText('Z wczoraj', { exact: true })).toBeVisible();
  await expect(page.getByText('Na 2 dni', { exact: true })).toBeVisible();
  await expect(page.getByText('Z kolacji', { exact: true })).toBeVisible();
  await expect(page.getByText('Też na śniadanie', { exact: true })).toBeVisible();
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await expectFigtreeLoaded(page);
}

test('meal plan draft desktop', async ({ page }) => {
  await page.setViewportSize(DESKTOP);
  await logIn(page);
  await openSeededDraft(page);

  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plan-draft-desktop.png', { fullPage: true });
});

test('meal plan draft mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await openSeededDraft(page);

  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plan-draft-mobile.png', { fullPage: true });
});

test('meal plan draft swap dialog', async ({ page }) => {
  await page.setViewportSize(DESKTOP);
  await logIn(page);
  await openSeededDraft(page);

  await page.getByRole('button', { name: /^Zmień przepis: .* – Obiad$/ }).nth(1).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Zmień przepis', { exact: true })).toBeVisible();
  await expect(dialog.getByText('Nie robimy (pusty posiłek)')).toBeVisible();
  await expect(dialog.getByText('Jak wczoraj')).toBeVisible();
  await expect(dialog.getByText('obecny')).toBeVisible();
  await expect(dialog.getByText(/jest już/).first()).toBeVisible();

  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('meal-plan-draft-swap-dialog.png');
});
