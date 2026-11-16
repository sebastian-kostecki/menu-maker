import { test, expect } from '@playwright/test';

test('user drafts, swaps, approves and reopens a weekly meal plan', async ({ page }, testInfo) => {
  // Two queued scalings plus four PDF renders (two with 21 recipe pages) take about a minute on their own.
  test.slow();

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

  // A fresh plan is a draft: no amounts, no shopping list, no PDF yet.
  const pdfButton = page.getByRole('button', { name: 'Pobierz PDF' });
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await expect(page.getByTestId('shopping-list')).toBeHidden();
  await expect(pdfButton).toBeHidden();

  // Swap the first Obiad for a recipe that is already used elsewhere in the week.
  const dinnerCell = grid.locator('tbody tr').first().locator('td').nth(1);
  const originalName = (await dinnerCell.locator('a').textContent())!.trim();
  await grid.getByRole('button', { name: /^Zmień przepis: .* – Obiad$/ }).first().click();
  const swapDialog = page.getByRole('dialog');
  const swapOption = swapDialog.getByRole('option').filter({ hasText: 'jest już' }).first();
  const swappedName = (await swapOption.locator('span.font-medium').textContent())!.trim();
  expect(swappedName).not.toBe(originalName);
  await swapOption.click();
  await expect(swapDialog).toBeHidden();
  await expect(dinnerCell.locator('a')).toHaveText(swappedName);

  await page.reload();
  await expect(recipeCells).toHaveCount(21);
  await expect(dinnerCell.locator('a')).toHaveText(swappedName);

  // Empty the first Śniadanie: "Nie robimy" turns it into a meal without a recipe.
  const breakfastCell = grid.locator('tbody tr').first().locator('td').nth(0);
  await grid.getByRole('button', { name: /^Zmień przepis: .* – Śniadanie$/ }).first().click();
  await page.getByRole('dialog').getByRole('option', { name: /Nie robimy \(pusty posiłek\)/ }).click();
  await expect(page.getByRole('dialog')).toBeHidden();
  await expect(breakfastCell.getByText('Bez posiłku')).toBeVisible();
  await expect(breakfastCell.getByRole('button', { name: 'Wybierz' })).toBeVisible();
  await expect(recipeCells).toHaveCount(20);

  await page.reload();
  await expect(recipeCells).toHaveCount(20);
  await expect(breakfastCell.getByText('Bez posiłku')).toBeVisible();

  const approve = async () => {
    await page.getByRole('button', { name: 'Zatwierdź' }).click();
    // The old draft grid also has 21 cells: wait for the pending state first, so the approval is proven.
    await expect(page.getByText('Generujemy jadłospis…')).toBeVisible();
  };
  await approve();
  await expect(recipeCells).toHaveCount(20, { timeout: 15_000 });
  await expect(dinnerCell.locator('a')).toHaveText(swappedName);
  await expect(breakfastCell.getByText('Bez posiłku')).toBeVisible();

  const expectScaledGrid = async () => {
    await expect(page.getByTestId('scaling-notice')).toBeHidden();
    const ingredientsToggle = grid.getByRole('button', { name: /^Składniki/ }).first();
    await ingredientsToggle.click();
    await expect(ingredientsToggle).toHaveAttribute('aria-expanded', 'true');
    await expect(grid.getByTestId('meal-ingredients').first()).toContainText('Mąka — 200 g');
  };

  const shoppingItems = page.getByTestId('shopping-list').getByRole('listitem');
  const expectShoppingList = async (expected: [string, string][]) => {
    await expect(shoppingItems).toHaveCount(3);
    for (const [index, [name, quantity]] of expected.entries()) {
      await expect(shoppingItems.nth(index)).toContainText(name);
      await expect(shoppingItems.nth(index)).toContainText(quantity);
    }
  };
  // 20 meals with a recipe: each contributes 2 szt. Jajka, 200 g Mąka and 100 ml Mleko.
  const totalsWithEmptyMeal: [string, string][] = [
    ['Jajka', '40 szt.'],
    ['Mąka', '4 kg'],
    ['Mleko', '2 l'],
  ];
  const totalsFull: [string, string][] = [
    ['Jajka', '42 szt.'],
    ['Mąka', '4,2 kg'],
    ['Mleko', '2,1 l'],
  ];
  await expectShoppingList(totalsWithEmptyMeal);
  await expectScaledGrid();

  const expectPdfDownload = async () => {
    await expect(pdfButton).toBeVisible();
    await pdfButton.click();
    const withRecipesHref = await page.getByRole('menuitem', { name: 'Z przepisami', exact: true }).getAttribute('href');
    const withoutRecipesHref = await page.getByRole('menuitem', { name: 'Bez przepisów', exact: true }).getAttribute('href');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toBeHidden();

    for (const [href, withRecipes] of [[withRecipesHref, true], [withoutRecipesHref, false]] as const) {
      const response = await page.request.get(href!);
      expect(response.status()).toBe(200);
      expect(response.headers()['content-type']).toContain('application/pdf');
      const disposition = response.headers()['content-disposition'];
      expect(disposition).toMatch(/^inline/);
      expect(disposition).toContain('jadlospis-');
      if (withRecipes) {
        expect(disposition).toContain('-z-przepisami');
      } else {
        expect(disposition).not.toContain('-z-przepisami');
      }
      expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
    }
  };
  await expectPdfDownload();

  // Back to a draft: the swapped recipe and the empty meal stay, the shopping list and the PDF go away.
  await page.getByRole('button', { name: 'Wróć do szkicu' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Wróć do szkicu', exact: true }).click();
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await expect(recipeCells).toHaveCount(20);
  await expect(breakfastCell.getByText('Bez posiłku')).toBeVisible();
  await expect(dinnerCell.locator('a')).toHaveText(swappedName);
  await expect(page.getByTestId('shopping-list')).toBeHidden();
  await expect(pdfButton).toBeHidden();

  // Fill the empty meal back with a recipe.
  await breakfastCell.getByRole('button', { name: 'Wybierz' }).click();
  const fillDialog = page.getByRole('dialog');
  await fillDialog.getByRole('option').filter({ hasText: 'jest już' }).first().click();
  await expect(fillDialog).toBeHidden();
  await expect(recipeCells).toHaveCount(21);

  await approve();
  await expect(recipeCells).toHaveCount(21, { timeout: 15_000 });
  await expect(dinnerCell.locator('a')).toHaveText(swappedName);
  await expectShoppingList(totalsFull);
  await expectScaledGrid();
  await expectPdfDownload();
});
