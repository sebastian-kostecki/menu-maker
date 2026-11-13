import { test, expect } from '@playwright/test';

test('user generates and regenerates a weekly meal plan', async ({ page }, testInfo) => {
  const email = `e2e-planner-${testInfo.project.name}@example.com`;

  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');

  await page.goto('/meal-plans');
  await page.getByRole('button', { name: 'Wygeneruj jadłospis' }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('button', { name: 'Wygeneruj', exact: true }).click();

  await expect(page).toHaveURL(/\/meal-plans\/\d+$/);

  const grid = page.getByTestId('meal-plan-grid');
  const recipeCells = grid.locator('tbody td a');
  await expect(recipeCells).toHaveCount(21, { timeout: 15_000 });
  for (const header of ['Śniadanie', 'Obiad', 'Kolacja']) {
    await expect(grid.getByRole('columnheader', { name: header })).toBeVisible();
  }

  await page.getByRole('button', { name: 'Regeneruj' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Regeneruj', exact: true }).click();

  await expect(recipeCells).toHaveCount(21, { timeout: 15_000 });
});
