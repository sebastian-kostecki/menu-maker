import { test, expect } from '@playwright/test';

test('user creates a recipe with a new ingredient', async ({ page }, testInfo) => {
  const email = `e2e-cook-${testInfo.project.name}@example.com`;

  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');

  await page.goto('/recipes/create');
  await expect(page.getByRole('heading', { name: 'Nowy przepis' })).toBeVisible();

  await page.getByLabel('Nazwa przepisu').fill('Kasza z E2E');
  await page.getByLabel('Kategoria').click();
  await page.getByRole('option', { name: 'Obiad' }).click();
  await page.getByLabel('Sposób przygotowania').fill('Ugotuj kaszę i podaj.');
  await page.getByLabel('Kaloryczność całkowita (kcal)').fill('350');
  await page.getByLabel('Liczba porcji').fill('2');

  const submit = page.getByRole('button', { name: 'Zapisz przepis' });
  await expect(submit).toBeDisabled();

  await page.getByRole('button', { name: 'Dodaj składnik' }).click();
  await page.getByRole('combobox', { name: 'Składnik' }).fill('Kasza jaglana E2E');
  await page.getByLabel('Ilość').fill('200');
  await page.getByLabel('Jednostka').click();
  await page.getByRole('option', { name: 'g', exact: true }).click();

  await expect(submit).toBeEnabled();
  await submit.click();

  await page.waitForURL('**/recipes');
  await page.getByText('Kasza z E2E').first().click();
  await page.waitForURL(/\/recipes\/\d+$/);

  await expect(page.getByText('Kasza jaglana E2E', { exact: true })).toBeVisible();
  await expect(page.getByText('200 g', { exact: true })).toBeVisible();
});
