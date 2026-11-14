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

  const expectScaledGrid = async () => {
    await expect(page.getByTestId('scaling-notice')).toBeHidden();
    await grid.locator('details').first().locator('summary').click();
    await expect(grid.getByTestId('meal-ingredients').first()).toContainText('Mąka — 200 g');
  };

  const shoppingItems = page.getByTestId('shopping-list').getByRole('listitem');
  const expectShoppingList = async () => {
    await expect(shoppingItems).toHaveCount(3);
    const expected: [string, string][] = [
      ['Jajka', '42 szt.'],
      ['Mąka', '4,2 kg'],
      ['Mleko', '2,1 l'],
    ];
    for (const [index, [name, quantity]] of expected.entries()) {
      await expect(shoppingItems.nth(index)).toContainText(name);
      await expect(shoppingItems.nth(index)).toContainText(quantity);
    }
  };
  await expectShoppingList();
  await expectScaledGrid();

  const pdfLink = page.getByRole('link', { name: 'Pobierz PDF' });
  const expectPdfDownload = async () => {
    await expect(pdfLink).toBeVisible();
    await expect(pdfLink).toHaveAttribute('target', '_blank');
    const response = await page.request.get((await pdfLink.getAttribute('href'))!);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/pdf');
    const disposition = response.headers()['content-disposition'];
    expect(disposition).toMatch(/^inline/);
    expect(disposition).toContain('jadlospis-');
    expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
  };
  await expectPdfDownload();

  await page.getByRole('button', { name: 'Regeneruj' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Regeneruj', exact: true }).click();

  // The old grid also has 21 cells: wait for the pending state first, so the regeneration is proven.
  await expect(page.getByText('Generujemy jadłospis…')).toBeVisible();
  await expect(pdfLink).toBeHidden();
  await expect(recipeCells).toHaveCount(21, { timeout: 15_000 });
  await expectShoppingList();
  await expectScaledGrid();
  await expectPdfDownload();
});
