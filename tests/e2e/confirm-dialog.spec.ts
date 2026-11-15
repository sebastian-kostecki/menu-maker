import { test, expect, type Page } from '@playwright/test';

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
}

test('recipe delete dialog focuses Anuluj and closes without deleting', async ({ page }, testInfo) => {
  await login(page, `e2e-planner-${testInfo.project.name}@example.com`);

  await page.goto('/recipes');
  const deleteButton = page.getByRole('button', { name: /^Usuń / }).first();
  await expect(deleteButton).toBeVisible();
  const rowLabel = await deleteButton.getAttribute('aria-label');
  const rowCount = await page.getByRole('button', { name: /^Usuń / }).count();

  const dialog = page.getByRole('alertdialog');

  await deleteButton.click();
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('heading', { name: 'Usuń przepis' })).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Anuluj' })).toBeFocused();

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(page.getByRole('button', { name: rowLabel! })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Usuń / })).toHaveCount(rowCount);

  await page.getByRole('button', { name: rowLabel! }).click();
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'Anuluj' }).click();
  await expect(dialog).toBeHidden();
  await expect(page.getByRole('button', { name: rowLabel! })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Usuń / })).toHaveCount(rowCount);
});

test('user deletes a family member through the confirmation dialog', async ({ page }, testInfo) => {
  await login(page, `e2e-cook-${testInfo.project.name}@example.com`);
  const name = `Delete E2E ${testInfo.project.name}`;

  await page.goto('/family-members/create');
  await page.getByLabel('Imię *').fill(name);
  await page.getByLabel('Data urodzenia *').fill('1990-01-01');
  await page.getByRole('combobox').click();
  await page.getByRole('option', { name: 'Mężczyzna' }).click();
  await page.getByLabel('Waga (kg)').fill('70');
  await page.getByLabel('Wzrost (cm)').fill('175');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
  await page.waitForURL('**/family-members');

  await expect(page.getByRole('cell', { name, exact: true })).toBeVisible();

  await page.getByRole('button', { name: `Usuń ${name}` }).click();
  const dialog = page.getByRole('alertdialog');
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'Usuń', exact: true }).click();

  await expect(page.getByRole('cell', { name, exact: true })).toHaveCount(0);
  await expect(page.getByText(`Członek rodziny "${name}" został usunięty`)).toBeVisible();
});
