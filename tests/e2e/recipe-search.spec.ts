import { test, expect } from '@playwright/test';

test('searching by an existing recipe name keeps that recipe in the list', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(`e2e-planner-${testInfo.project.name}@example.com`);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');

  await page.goto('/recipes');
  const firstName = (await page.getByRole('row').nth(1).getByRole('link').first().textContent())!.trim();

  await page.getByRole('searchbox', { name: 'Szukaj przepisów' }).fill(firstName);
  await page.waitForURL((url) => url.searchParams.get('search') === firstName);

  expect(new URL(page.url()).searchParams.has('category')).toBe(false);
  await expect(page.getByRole('link', { name: firstName, exact: true }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Brak wyników' })).toBeHidden();
});
