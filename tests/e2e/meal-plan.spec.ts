import { test, expect, type Locator } from '@playwright/test';

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

  // Typing a recipe name and pressing Enter picks a recipe: the "Nie robimy" row is hidden while searching.
  const breakfastCell = grid.locator('tbody tr').first().locator('td').nth(0);
  await grid.getByRole('button', { name: /^Zmień przepis: .* – Śniadanie$/ }).first().click();
  const searchName = (await swapDialog.getByRole('option').filter({ hasNotText: 'Nie robimy' }).first().locator('span.font-medium').textContent())!.trim();
  await swapDialog.getByRole('combobox', { name: 'Szukaj przepisu' }).fill(searchName);
  await expect(swapDialog.getByRole('option', { name: /Nie robimy/ })).toHaveCount(0);
  await page.keyboard.press('Enter');
  await expect(swapDialog).toBeHidden();
  await expect(breakfastCell.getByText('Bez posiłku')).toBeHidden();
  await expect(recipeCells).toHaveCount(21);

  // Empty the first Śniadanie: "Nie robimy" turns it into a meal without a recipe.
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
    // The old draft grid also has 21 cells, and the pending state is too short-lived to assert on
    // (the queue job takes ~300 ms): the approve request itself proves the approval.
    const approval = page.waitForResponse(
      (response) => response.request().method() === 'POST' && /\/meal-plans\/\d+\/approve$/.test(response.url()),
    );
    await page.getByRole('button', { name: 'Zatwierdź' }).click();
    expect((await approval).status()).toBeLessThan(400);
    // The shopping list only exists on an approved plan: wait for the generation job to finish.
    await expect(page.getByTestId('shopping-list')).toBeVisible({ timeout: 30_000 });
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
    const menuOnlyHref = await page.getByRole('menuitem', { name: 'Sam jadłospis', exact: true }).getAttribute('href');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toBeHidden();

    for (const [href, variant] of [[withRecipesHref, '-z-przepisami'], [withoutRecipesHref, null], [menuOnlyHref, '-sam-jadlospis']] as const) {
      const response = await page.request.get(href!);
      expect(response.status()).toBe(200);
      expect(response.headers()['content-type']).toContain('application/pdf');
      const disposition = response.headers()['content-disposition'];
      expect(disposition).toMatch(/^inline/);
      expect(disposition).toContain('jadlospis-');
      if (variant) {
        expect(disposition).toContain(variant);
      } else {
        expect(disposition).not.toMatch(/-z-przepisami|-sam-jadlospis/);
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

  // Back to a draft and link Tuesday's Obiad to Monday's: both badges prove the link, not the name alone.
  const mondayDinner = dinnerCell;
  const tuesdayDinner = grid.locator('tbody tr').nth(1).locator('td').nth(1);
  await page.getByRole('button', { name: 'Wróć do szkicu' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Wróć do szkicu', exact: true }).click();
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await tuesdayDinner.getByRole('button', { name: /^Zmień przepis: .* – Obiad$/ }).click();
  const linkDialog = page.getByRole('dialog');
  await linkDialog.getByRole('option', { name: /Jak wczoraj/ }).click();
  await expect(linkDialog).toBeHidden();
  const expectLinkedPair = async () => {
    await expect(tuesdayDinner.locator('a')).toHaveText(swappedName);
    await expect(tuesdayDinner.getByText('Z wczoraj', { exact: true })).toBeVisible();
    await expect(mondayDinner.getByText('Na 2 dni', { exact: true })).toBeVisible();
    await expect(recipeCells).toHaveCount(21);
  };
  await expectLinkedPair();

  await page.reload();
  await expectLinkedPair();

  // Approve: Monday's Obiad holds the amounts for both days, Tuesday's has none, the totals stay the same.
  await approve();
  await expect(recipeCells).toHaveCount(21, { timeout: 15_000 });
  await expectLinkedPair();
  await expectShoppingList(totalsFull);
  const mondayToggle = mondayDinner.getByRole('button', { name: /^Składniki/ });
  await mondayToggle.click();
  await expect(mondayToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(mondayDinner.getByTestId('meal-ingredients')).toContainText('Mąka — 400 g');
  await expect(tuesdayDinner.getByRole('button', { name: /^Składniki/ })).toHaveCount(0);

  // Back to a draft and swap Monday's Obiad: Tuesday's Obiad is emptied and both badges go.
  await page.getByRole('button', { name: 'Wróć do szkicu' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Wróć do szkicu', exact: true }).click();
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await expectLinkedPair();
  await mondayDinner.getByRole('button', { name: /^Zmień przepis: .* – Obiad$/ }).click();
  const unlinkDialog = page.getByRole('dialog');
  // The current recipe is also "jest już" now, and picking it would keep the link.
  await unlinkDialog.getByRole('option').filter({ hasText: 'jest już', hasNotText: 'obecny' }).first().click();
  await expect(unlinkDialog).toBeHidden();
  await expect(tuesdayDinner.getByText('Bez posiłku')).toBeVisible();
  await expect(tuesdayDinner.getByRole('button', { name: 'Wybierz' })).toBeVisible();
  await expect(page.getByText('Z wczoraj', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Na 2 dni', { exact: true })).toHaveCount(0);
  await expect(recipeCells).toHaveCount(20);

  // Link Tuesday's Śniadanie to Monday's Kolacja: the cross-category pair shows its own two badges.
  const mondayKolacja = grid.locator('tbody tr').first().locator('td').nth(2);
  const tuesdaySniadanie = grid.locator('tbody tr').nth(1).locator('td').nth(0);
  const kolacjaName = (await mondayKolacja.locator('a').textContent())!.trim();
  await tuesdaySniadanie.getByRole('button', { name: /^Zmień przepis: .* – Śniadanie$/ }).click();
  const sniadanieLinkDialog = page.getByRole('dialog');
  await sniadanieLinkDialog.getByRole('option', { name: /Z kolacji/ }).click();
  await expect(sniadanieLinkDialog).toBeHidden();
  const expectKolacjaPair = async () => {
    await expect(tuesdaySniadanie.locator('a')).toHaveText(kolacjaName);
    await expect(tuesdaySniadanie.getByText('Z kolacji', { exact: true })).toBeVisible();
    await expect(mondayKolacja.getByText('Też na śniadanie', { exact: true })).toBeVisible();
    await expect(recipeCells).toHaveCount(20);
  };
  await expectKolacjaPair();

  await page.reload();
  await expectKolacjaPair();

  // Reopen Tuesday's Śniadanie: the "Z kolacji" item carries its hint and is marked as the current choice.
  await tuesdaySniadanie.getByRole('button', { name: /^Zmień przepis: .* – Śniadanie$/ }).click();
  const linkedSniadanieOption = sniadanieLinkDialog.getByRole('option', { name: /Z kolacji/ });
  await expect(linkedSniadanieOption).toContainText(`Z kolacji: ${kolacjaName}`);
  await expect(linkedSniadanieOption).toContainText('Gotujemy raz, zostaje na śniadanie');
  await expect(linkedSniadanieOption).toContainText('obecny');
  // The always-open recipe list hides the rest of the dialog from the accessibility tree and keeps Escape to itself.
  await sniadanieLinkDialog.getByRole('button', { name: 'Zamknij', includeHidden: true }).click();
  await expect(sniadanieLinkDialog).toBeHidden();

  // Approve: Monday's Kolacja holds the amounts for both meals, Tuesday's Śniadanie has none, the totals stay the same.
  await approve();
  await expect(recipeCells).toHaveCount(20, { timeout: 15_000 });
  await expectKolacjaPair();
  await expectShoppingList(totalsWithEmptyMeal);
  const kolacjaToggle = mondayKolacja.getByRole('button', { name: /^Składniki/ });
  await kolacjaToggle.click();
  await expect(kolacjaToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(mondayKolacja.getByTestId('meal-ingredients')).toContainText('Mąka — 400 g');
  await expect(tuesdaySniadanie.getByRole('button', { name: /^Składniki/ })).toHaveCount(0);

  // Back to a draft and swap Monday's Kolacja: it warns about Tuesday's Śniadanie, which is emptied, and both badges go.
  await page.getByRole('button', { name: 'Wróć do szkicu' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Wróć do szkicu', exact: true }).click();
  await expect(page.getByText('Szkic', { exact: true }).first()).toBeVisible();
  await expectKolacjaPair();
  await mondayKolacja.getByRole('button', { name: /^Zmień przepis: .* – Kolacja$/ }).click();
  const kolacjaUnlinkDialog = page.getByRole('dialog');
  await expect(kolacjaUnlinkDialog.getByText(/^Zmiana opróżni śniadanie z .+, które jest „z kolacji”\.$/)).toBeVisible();
  // The current recipe is also "jest już" now, and picking it would keep the link.
  await kolacjaUnlinkDialog.getByRole('option').filter({ hasText: 'jest już', hasNotText: 'obecny' }).first().click();
  await expect(kolacjaUnlinkDialog).toBeHidden();
  await expect(tuesdaySniadanie.getByText('Bez posiłku')).toBeVisible();
  await expect(tuesdaySniadanie.getByRole('button', { name: 'Wybierz' })).toBeVisible();
  await expect(page.getByText('Z kolacji', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Też na śniadanie', { exact: true })).toHaveCount(0);
  await expect(recipeCells).toHaveCount(19);

  // Drag Friday's Śniadanie onto Friday's Kolacja: the two recipes trade places and each cell tags the other category.
  const fridayRow = grid.locator('tbody tr').nth(4);
  const fridaySniadanie = fridayRow.locator('td').nth(0);
  const fridayKolacja = fridayRow.locator('td').nth(2);
  const fridaySniadanieName = (await fridaySniadanie.locator('a').textContent())!.trim();
  const fridayKolacjaName = (await fridayKolacja.locator('a').textContent())!.trim();
  expect(fridayKolacjaName).not.toBe(fridaySniadanieName);

  // dragTo scrolls a cell that is off screen into view after the mouse went down, and the browser then takes the
  // drag source from under the old pointer position: a viewport that shows the whole grid keeps the page still.
  await page.setViewportSize({ width: 1280, height: 1800 });
  await expect(grid.locator('tbody tr').last()).toBeInViewport();

  const waitForMove = () =>
    page.waitForResponse((response) => response.request().method() === 'POST' && /\/meals\/\d+\/move$/.test(response.url()));
  const dragCell = async (source: Locator, target: Locator) => {
    const moved = waitForMove();
    await source.locator('div[draggable="true"]').dragTo(target.locator('> div'));
    expect((await moved).status()).toBeLessThan(400);
  };

  const expectDragSwapped = async () => {
    await expect(fridaySniadanie.locator('a')).toHaveText(fridayKolacjaName);
    await expect(fridayKolacja.locator('a')).toHaveText(fridaySniadanieName);
    await expect(fridaySniadanie.getByText('Kolacja', { exact: true })).toBeVisible();
    await expect(fridayKolacja.getByText('Śniadanie', { exact: true })).toBeVisible();
    await expect(recipeCells).toHaveCount(19);
  };
  await dragCell(fridaySniadanie, fridayKolacja);
  await expectDragSwapped();

  await page.reload();
  await expectDragSwapped();

  // Drag a filled cell onto Tuesday's empty Śniadanie: the recipe moves and the source cell is left empty.
  await dragCell(fridaySniadanie, tuesdaySniadanie);
  await expect(tuesdaySniadanie.locator('a')).toHaveText(fridayKolacjaName);
  await expect(tuesdaySniadanie.getByText('Kolacja', { exact: true })).toBeVisible();
  await expect(fridaySniadanie.getByText('Bez posiłku')).toBeVisible();
  await expect(fridaySniadanie.getByRole('button', { name: 'Wybierz' })).toBeVisible();
  await expect(recipeCells).toHaveCount(19);

  await page.reload();
  await expect(tuesdaySniadanie.locator('a')).toHaveText(fridayKolacjaName);
  await expect(fridaySniadanie.getByText('Bez posiłku')).toBeVisible();
  await expect(recipeCells).toHaveCount(19);

  // The dialog also offers recipes of other categories, under their own heading: a Kolacja recipe goes into Wednesday's Śniadanie.
  const wednesdaySniadanie = grid.locator('tbody tr').nth(2).locator('td').nth(0);
  await wednesdaySniadanie.getByRole('button', { name: /^Zmień przepis: .* – Śniadanie$/ }).click();
  const crossDialog = page.getByRole('dialog');
  const kolacjaGroup = crossDialog.getByTestId('swap-group-dinner');
  await expect(kolacjaGroup).toContainText('Kolacja');
  const crossOption = kolacjaGroup.getByRole('option').first();
  const crossName = (await crossOption.locator('span.font-medium').textContent())!.trim();
  await crossOption.click();
  await expect(crossDialog).toBeHidden();
  await expect(wednesdaySniadanie.locator('a')).toHaveText(crossName);
  await expect(wednesdaySniadanie.getByText('Kolacja', { exact: true })).toBeVisible();

  await page.reload();
  await expect(wednesdaySniadanie.locator('a')).toHaveText(crossName);
  await expect(wednesdaySniadanie.getByText('Kolacja', { exact: true })).toBeVisible();
});
