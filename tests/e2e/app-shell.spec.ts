import { test, expect, type Page, type TestInfo } from '@playwright/test';

async function login(page: Page, testInfo: TestInfo) {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(`e2e-planner-${testInfo.project.name}@example.com`);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
}

test('sidebar collapse persists across navigation and reload', async ({ page }, testInfo) => {
  await login(page, testInfo);

  const nav = page.getByRole('navigation', { name: 'Główna nawigacja' });
  await page.getByRole('button', { name: 'Zwiń panel boczny' }).click();
  await expect(page.getByRole('button', { name: 'Rozwiń panel boczny' })).toBeVisible();

  await nav.getByRole('link', { name: 'Przepisy', exact: true }).click();
  await page.waitForURL('**/recipes');
  await expect(page.getByRole('button', { name: 'Rozwiń panel boczny' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'Przepisy', exact: true })).toHaveAttribute('aria-current', 'page');

  await page.reload();
  await expect(page.getByRole('button', { name: 'Rozwiń panel boczny' })).toBeVisible();
});

test('mobile navigation drawer opens, closes on Esc and navigates', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, testInfo);

  const hamburger = page.getByRole('button', { name: 'Otwórz nawigację' });
  const dialog = page.getByRole('dialog');

  await hamburger.click();
  await expect(dialog).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(hamburger).toBeFocused();

  await hamburger.click();
  await expect(dialog).toBeVisible();
  await dialog
    .getByRole('navigation', { name: 'Główna nawigacja' })
    .getByRole('link', { name: 'Jadłospisy', exact: true })
    .click();
  await expect(page).toHaveURL(/\/meal-plans/);
  await expect(dialog).toBeHidden();
});

test('user logs out through the user menu', async ({ page }, testInfo) => {
  await login(page, testInfo);

  await page.getByRole('button', { name: 'Menu użytkownika' }).click();
  await page.getByRole('menuitem', { name: 'Wyloguj' }).click();
  await page.waitForURL(url => url.pathname === '/');

  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login/);
});
