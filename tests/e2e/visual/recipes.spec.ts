import { test, expect, type Page } from '@playwright/test';
import { logInAsVisualUser as logIn } from './support/login';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

const MOBILE = { width: 390, height: 844 };

// Newest recipe of the deterministic list in E2eMealPlanSeeder.
const NEWEST_RECIPE = 'Jajecznica ze szczypiorkiem';

test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2026-06-15T10:00:00'));
});

async function openIndex(page: Page) {
  await page.goto('/recipes');
  await expect(page.getByRole('heading', { level: 1, name: 'Przepisy' })).toBeVisible();
  await expect(page.getByRole('link', { name: NEWEST_RECIPE, exact: true })).toBeVisible();
}

async function openShow(page: Page) {
  await openIndex(page);
  await page.getByRole('link', { name: NEWEST_RECIPE, exact: true }).click();
  await expect(page.getByRole('heading', { level: 1, name: NEWEST_RECIPE, exact: true })).toBeVisible();
}

async function openCreate(page: Page) {
  await page.goto('/recipes/create');
  await expect(page.getByRole('heading', { level: 1, name: 'Nowy przepis' })).toBeVisible();
}

test('recipes index desktop', async ({ page }) => {
  await logIn(page);
  await openIndex(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipes-index-desktop.png', { fullPage: true });
});

test('recipes index mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await openIndex(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipes-index-mobile.png', { fullPage: true });
});

test('recipes index no results', async ({ page }) => {
  await logIn(page);
  await openIndex(page);
  await page.getByRole('searchbox', { name: 'Szukaj przepisów' }).fill('zzzzzz');
  await expect(page.getByRole('heading', { name: 'Brak wyników', exact: true })).toBeVisible();
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipes-index-no-results.png', { fullPage: true });
});

test('recipe show desktop', async ({ page }) => {
  await logIn(page);
  await openShow(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipe-show-desktop.png', { fullPage: true });
});

test('recipe show mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await openShow(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipe-show-mobile.png', { fullPage: true });
});

test('recipe form create desktop', async ({ page }) => {
  await logIn(page);
  await openCreate(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipe-form-create-desktop.png', { fullPage: true });
});

test('recipe form create mobile', async ({ page }) => {
  await page.setViewportSize(MOBILE);
  await logIn(page);
  await openCreate(page);
  await page.mouse.move(0, 0);
  await expect(page).toHaveScreenshot('recipe-form-create-mobile.png', { fullPage: true });
});
