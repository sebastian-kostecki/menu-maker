import { test, expect } from '@playwright/test';

test('user adds, validates, edits and deletes a family member', async ({ page }, testInfo) => {
  const email = `e2e-family-${testInfo.project.name}@example.com`;

  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');

  await page.goto('/family-members');
  await expect(page.getByRole('heading', { name: 'Członkowie rodziny' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Brak członków rodziny' })).toBeVisible();

  await page.getByRole('button', { name: 'Dodaj członka', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Imię').fill('Ewa E2E');
  await dialog.getByLabel('Data urodzenia').fill('1990-05-01');
  await dialog.getByLabel('Płeć').click();
  await page.getByRole('option', { name: 'Kobieta' }).click();
  await dialog.getByRole('button', { name: 'Zapisz', exact: true }).click();

  await expect(dialog.getByText('Podaj wagę (wymagana od 19 lat).')).toBeVisible();
  await expect(dialog).toBeVisible();

  await dialog.getByLabel('Waga').fill('60');
  await dialog.getByLabel('Wzrost').fill('165');
  await dialog.getByRole('button', { name: 'Zapisz', exact: true }).click();

  const createdRow = page.getByRole('row', { name: /Ewa E2E/ });
  await expect(createdRow).toBeVisible();
  await expect(createdRow.getByText('1 maja 1990')).toBeVisible();
  await expect(createdRow.getByText('60 kg')).toBeVisible();

  await page.getByRole('button', { name: 'Edytuj Ewa E2E' }).click();
  await expect(dialog.getByLabel('Data urodzenia')).toHaveValue('1990-05-01');
  await dialog.getByLabel('Imię').fill('Ewa Edytowana');
  await dialog.getByRole('button', { name: 'Zapisz', exact: true }).click();

  await expect(page.getByRole('row', { name: /Ewa Edytowana/ })).toBeVisible();
  await expect(page.getByRole('row', { name: /Ewa E2E/ })).toHaveCount(0);

  await page.getByRole('button', { name: 'Usuń Ewa Edytowana' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Usuń', exact: true }).click();

  await expect(page.getByRole('heading', { name: 'Brak członków rodziny' })).toBeVisible();
});
