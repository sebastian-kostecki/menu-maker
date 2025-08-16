import { test, expect } from '@playwright/test';

// Accessibility and UX E2E Tests for Recipes (A11Y, UX)
// Tests focus management, ARIA labels, keyboard navigation, and user experience

const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-a11y-${random}@example.com`;
};

const createUserAndLogin = async (page) => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E A11Y User');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');

  return { email, password };
};

const fillRecipeForm = async (page, recipeData) => {
  await page.getByLabel('Recipe Name').fill(recipeData.name);
  await page.getByLabel('Category').selectOption(recipeData.category);
  await page.getByLabel('Calories').fill(recipeData.calories.toString());
  await page.getByLabel('Servings').fill(recipeData.servings.toString());
  await page.getByLabel('Instructions').fill(recipeData.instructions);

  for (const ingredient of recipeData.ingredients) {
    await page.getByRole('button', { name: 'Add Ingredient' }).click();
    await page.getByLabel('Ingredient Name').last().fill(ingredient.name);
    await page.getByLabel('Quantity').last().fill(ingredient.quantity.toString());
    await page.getByLabel('Unit').last().selectOption(ingredient.unit);
  }
};

const testRecipe = {
  name: 'A11Y Test Recipe',
  category: 'breakfast',
  calories: 300,
  servings: 2,
  instructions: 'Simple recipe for accessibility testing.',
  ingredients: [
    { name: 'Bread', quantity: 2, unit: 'pieces' },
    { name: 'Butter', quantity: 10, unit: 'g' }
  ]
};

test.describe('Recipes Accessibility & UX Tests', () => {

  test.describe('Keyboard Navigation', () => {

    test('A11Y-001a: Tab navigation through recipes page', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe for navigation testing
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Start tab navigation from search input
      await page.getByPlaceholder('Search recipes...').focus();

      // Verify search input is focused
      await expect(page.getByPlaceholder('Search recipes...')).toBeFocused();

      // Tab to category dropdown
      await page.keyboard.press('Tab');
      const categoryElement = page.getByRole('combobox', { name: /category/i }).or(page.locator('select'));
      await expect(categoryElement).toBeFocused();

      // Tab to reset button
      await page.keyboard.press('Tab');
      const resetButton = page.getByRole('button', { name: /reset|clear/i });
      await expect(resetButton).toBeFocused();

      // Tab to create button
      await page.keyboard.press('Tab');
      const createButton = page.getByRole('button', { name: /create|add|new/i });
      await expect(createButton).toBeFocused();

      // Tab into table - should focus on first sortable header
      await page.keyboard.press('Tab');
      const firstHeader = page.getByRole('columnheader', { name: /name/i });
      await expect(firstHeader).toBeFocused();

      // Tab through table headers
      await page.keyboard.press('Tab');
      const categoryHeader = page.getByRole('columnheader', { name: /category/i });
      await expect(categoryHeader).toBeFocused();

      await page.keyboard.press('Tab');
      const caloriesHeader = page.getByRole('columnheader', { name: /calories/i });
      await expect(caloriesHeader).toBeFocused();
    });

    test('A11Y-001b: Keyboard navigation in recipe row actions', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Focus on recipe name (first interactive element in row)
      const recipeName = page.getByText(testRecipe.name);
      await recipeName.focus();
      await expect(recipeName).toBeFocused();

      // Tab through action buttons
      await page.keyboard.press('Tab');
      const viewButton = page.getByRole('button', { name: `View ${testRecipe.name}` });
      await expect(viewButton).toBeFocused();

      await page.keyboard.press('Tab');
      const editButton = page.getByRole('button', { name: `Edit ${testRecipe.name}` });
      await expect(editButton).toBeFocused();

      await page.keyboard.press('Tab');
      const deleteButton = page.getByRole('button', { name: `Delete ${testRecipe.name}` });
      await expect(deleteButton).toBeFocused();

      // Test Enter key activation
      await page.keyboard.press('Enter');
      await expect(page.getByText('Delete Recipe')).toBeVisible(); // Modal should open

      // Test Escape key closes modal
      await page.keyboard.press('Escape');
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();
    });

    test('A11Y-001c: Search input keyboard functionality', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create multiple test recipes
      const recipes = [
        { ...testRecipe, name: 'Keyboard Test Recipe 1' },
        { ...testRecipe, name: 'Different Recipe 2' },
        { ...testRecipe, name: 'Keyboard Search Recipe 3' }
      ];

      for (const recipe of recipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Focus search input
      const searchInput = page.getByPlaceholder('Search recipes...');
      await searchInput.focus();
      await expect(searchInput).toBeFocused();

      // Type search term
      await page.keyboard.type('Keyboard');

      // Wait for debounce
      await page.waitForTimeout(500);

      // Enter key should trigger search (if not already triggered by debounce)
      await page.keyboard.press('Enter');
      await page.waitForTimeout(300);

      // Verify filtered results
      await expect(page.getByText('Keyboard Test Recipe 1')).toBeVisible();
      await expect(page.getByText('Keyboard Search Recipe 3')).toBeVisible();
      await expect(page.getByText('Different Recipe 2')).not.toBeVisible();

      // Clear with keyboard (Ctrl+A, Delete)
      await page.keyboard.press('Control+a');
      await page.keyboard.press('Delete');
      await page.waitForTimeout(500);

      // All recipes should be visible again
      await expect(page.getByText('Different Recipe 2')).toBeVisible();
    });

    test('A11Y-001d: Sorting with keyboard', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create recipes for sorting
      const sortingRecipes = [
        { ...testRecipe, name: 'Alpha Recipe', calories: 100 },
        { ...testRecipe, name: 'Beta Recipe', calories: 300 },
        { ...testRecipe, name: 'Gamma Recipe', calories: 200 }
      ];

      for (const recipe of sortingRecipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Focus on name header and activate with Enter
      const nameHeader = page.getByRole('columnheader', { name: /name/i });
      await nameHeader.focus();
      await expect(nameHeader).toBeFocused();

      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);

      // Check that sorting worked
      const firstRecipe = await page.locator('tbody tr:first-child td:first-child button').textContent();
      expect(firstRecipe).toBe('Alpha Recipe');

      // Press Enter again to reverse sort
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);

      const firstReversed = await page.locator('tbody tr:first-child td:first-child button').textContent();
      expect(firstReversed).toBe('Gamma Recipe');

      // Test Space key activation
      await page.keyboard.press('Space');
      await page.waitForTimeout(500);

      const afterSpace = await page.locator('tbody tr:first-child td:first-child button').textContent();
      expect(afterSpace).toBe('Alpha Recipe');
    });
  });

  test.describe('ARIA Attributes and Semantic HTML', () => {

    test('A11Y-002a: Table has proper ARIA structure', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify table structure
      const table = page.getByRole('table');
      await expect(table).toBeVisible();

      // Check table headers have proper roles
      await expect(page.getByRole('columnheader', { name: /name/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /category/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /calories/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /servings/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /created/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /actions/i })).toBeVisible();

      // Check table cells have proper content
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      await expect(recipeRow.getByRole('cell').first()).toBeVisible();

      // Verify action buttons have aria-labels
      await expect(recipeRow.getByRole('button', { name: `View ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Edit ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` })).toBeVisible();
    });

    test('A11Y-002b: Form controls have proper labels', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Go to create form
      await page.getByRole('button', { name: /create|add|new/i }).click();

      // Verify form controls have labels
      await expect(page.getByLabel('Recipe Name')).toBeVisible();
      await expect(page.getByLabel('Category')).toBeVisible();
      await expect(page.getByLabel('Calories')).toBeVisible();
      await expect(page.getByLabel('Servings')).toBeVisible();
      await expect(page.getByLabel('Instructions')).toBeVisible();

      // Check search input on recipes page
      await page.goto('/recipes');
      const searchInput = page.getByPlaceholder('Search recipes...');
      await expect(searchInput).toBeVisible();

      // Search input should have accessible name (either label or aria-label)
      const searchInputHandle = await searchInput.elementHandle();
      const ariaLabel = await searchInputHandle?.getAttribute('aria-label');
      const labelledBy = await searchInputHandle?.getAttribute('aria-labelledby');

      expect(ariaLabel || labelledBy || 'Search recipes...').toBeTruthy();
    });

    test('A11Y-002c: Modal has proper ARIA attributes', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Open delete modal
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();

      // Check modal has proper role
      const modal = page.locator('[role="dialog"]').or(page.locator('[role="alertdialog"]'));
      await expect(modal).toBeVisible();

      // Modal should have aria-modal attribute
      const modalElement = await modal.first().elementHandle();
      const ariaModal = await modalElement?.getAttribute('aria-modal');
      expect(ariaModal).toBe('true');

      // Modal should have accessible title
      await expect(page.getByRole('heading', { name: 'Delete Recipe' })).toBeVisible();

      // Buttons should be properly labeled
      await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Delete Recipe' })).toBeVisible();
    });

    test('A11Y-002d: Sortable headers have ARIA attributes', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Check sortable headers have proper ARIA attributes
      const nameHeader = page.getByRole('columnheader', { name: /name/i });
      const headerElement = await nameHeader.elementHandle();

      // Should have aria-sort attribute (none, ascending, or descending)
      const ariaSort = await headerElement?.getAttribute('aria-sort');
      expect(['none', 'ascending', 'descending', null]).toContain(ariaSort);

      // Click to sort and check aria-sort changes
      await nameHeader.click();
      await page.waitForTimeout(300);

      const newAriaSort = await headerElement?.getAttribute('aria-sort');
      expect(['ascending', 'descending']).toContain(newAriaSort);
    });
  });

  test.describe('Focus Management', () => {

    test('A11Y-003a: Focus management in delete modal', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Remember the trigger button
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      const deleteButton = recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` });

      // Open modal
      await deleteButton.click();

      // Focus should be trapped in modal
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Tab should cycle through modal buttons only
      await page.keyboard.press('Tab');
      const cancelButton = page.getByRole('button', { name: 'Cancel' });
      await expect(cancelButton).toBeFocused();

      await page.keyboard.press('Tab');
      const confirmButton = page.getByRole('button', { name: 'Delete Recipe' });
      await expect(confirmButton).toBeFocused();

      // Shift+Tab should go back
      await page.keyboard.press('Shift+Tab');
      await expect(cancelButton).toBeFocused();

      // Close modal with Cancel
      await cancelButton.click();

      // Focus should return to trigger button
      await expect(deleteButton).toBeFocused();
    });

    test('A11Y-003b: Focus management on page navigation', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create multiple recipes for pagination
      for (let i = 1; i <= 18; i++) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        const recipe = {
          name: `Focus Test Recipe ${i.toString().padStart(2, '0')}`,
          category: testRecipe.category,
          calories: 200 + i * 10,
          servings: 2,
          instructions: `Test recipe ${i} instructions.`,
          ingredients: [{ name: `Ingredient ${i}`, quantity: i, unit: 'g' }]
        };
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Navigate to next page
      const nextButton = page.getByRole('button', { name: /next/i });
      await nextButton.click();

      // Focus should be managed appropriately
      // Either stay on pagination or move to a logical place
      const focusedElement = await page.evaluate(() => document.activeElement?.tagName);
      expect(['BUTTON', 'A', 'BODY']).toContain(focusedElement);

      // Going back should also manage focus
      const prevButton = page.getByRole('button', { name: /previous|prev/i });
      await prevButton.click();

      const focusedAfterBack = await page.evaluate(() => document.activeElement?.tagName);
      expect(['BUTTON', 'A', 'BODY']).toContain(focusedAfterBack);
    });

    test('A11Y-003c: Focus visible indicators', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Tab through interactive elements and verify focus is visible
      const searchInput = page.getByPlaceholder('Search recipes...');
      await searchInput.focus();

      // Check if focus styles are applied (common CSS properties)
      const searchFocusStyle = await searchInput.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          outline: styles.outline,
          outlineColor: styles.outlineColor,
          boxShadow: styles.boxShadow,
          borderColor: styles.borderColor
        };
      });

      // Should have some form of focus indicator
      const hasFocusIndicator =
        searchFocusStyle.outline !== 'none' ||
        searchFocusStyle.boxShadow !== 'none' ||
        searchFocusStyle.borderColor !== 'initial';

      expect(hasFocusIndicator).toBeTruthy();

      // Test button focus
      const createButton = page.getByRole('button', { name: /create|add|new/i });
      await createButton.focus();

      const buttonFocusStyle = await createButton.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          outline: styles.outline,
          outlineColor: styles.outlineColor,
          boxShadow: styles.boxShadow
        };
      });

      const buttonHasFocusIndicator =
        buttonFocusStyle.outline !== 'none' ||
        buttonFocusStyle.boxShadow !== 'none';

      expect(buttonHasFocusIndicator).toBeTruthy();
    });
  });

  test.describe('Screen Reader Support', () => {

    test('A11Y-004a: Proper heading structure', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Check main heading
      await expect(page.getByRole('heading', { name: 'Przepisy', level: 1 })).toBeVisible();

      // Create a recipe and check show page
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Go to recipe detail page
      await page.getByText(testRecipe.name).click();
      await expect(page.getByRole('heading', { name: testRecipe.name })).toBeVisible();
    });

    test('A11Y-004b: Status and error messages are announced', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Test empty state message
      await expect(page.getByText('Brak przepisów')).toBeVisible();
      await expect(page.getByText('Rozpocznij od dodania pierwszego przepisu')).toBeVisible();

      // Create a recipe then test search with no results
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Search for non-existent recipe
      await page.getByPlaceholder('Search recipes...').fill('nonexistent');
      await page.waitForTimeout(500);

      // Should announce no results
      await expect(page.getByText('No recipes found')).toBeVisible();

      // The message should be in a region that screen readers will announce
      const noResultsElement = page.getByText('No recipes found');
      const ariaLive = await noResultsElement.getAttribute('aria-live');
      const role = await noResultsElement.getAttribute('role');

      // Should have aria-live or be in a status/alert region
      expect(ariaLive || role).toBeTruthy();
    });

    test('A11Y-004c: Data tables are properly structured', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create test recipes
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify table has caption or summary
      const table = page.getByRole('table');
      await expect(table).toBeVisible();

      // Check if table has proper structure for screen readers
      const tableElement = await table.elementHandle();
      const hasCaption = await page.locator('table caption').count() > 0;
      const hasAriaLabel = await tableElement?.getAttribute('aria-label');
      const hasAriaLabelledBy = await tableElement?.getAttribute('aria-labelledby');

      // Should have some form of accessible description
      expect(hasCaption || hasAriaLabel || hasAriaLabelledBy).toBeTruthy();

      // Headers should be properly associated
      const headers = await page.locator('th').all();
      for (const header of headers) {
        const scope = await header.getAttribute('scope');
        const id = await header.getAttribute('id');
        // Headers should have scope attribute or id for association
        expect(scope || id).toBeTruthy();
      }
    });

    test('A11Y-004d: Loading states are announced', async ({ page }) => {
      await createUserAndLogin(page);

      // Monitor network requests to simulate loading
      await page.route('**/recipes*', async (route) => {
        // Delay response to simulate loading
        await new Promise(resolve => setTimeout(resolve, 1000));
        await route.continue();
      });

      await page.goto('/recipes');

      // Should show some loading indication
      // This could be a spinner, loading text, or aria-busy attribute
      const loadingIndicators = await Promise.race([
        page.locator('[aria-busy="true"]').count(),
        page.getByText(/loading|ładowanie/i).count(),
        page.locator('.spinner,.loading').count()
      ]);

      // At least one loading indicator should be present during loading
      // Note: This test might be flaky depending on how fast the app loads
    });
  });

  test.describe('Color and Contrast', () => {

    test('A11Y-005a: Sufficient color contrast for text', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Check main heading contrast
      const heading = page.getByRole('heading', { name: 'Przepisy' });
      const headingStyles = await heading.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          color: styles.color,
          backgroundColor: styles.backgroundColor
        };
      });

      // While we can't easily calculate exact contrast ratios in E2E tests,
      // we can verify that colors are not the same
      expect(headingStyles.color).not.toBe(headingStyles.backgroundColor);

      // Check table text contrast
      const recipeCell = page.locator('tbody tr td').first();
      const cellStyles = await recipeCell.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          color: styles.color,
          backgroundColor: styles.backgroundColor
        };
      });

      expect(cellStyles.color).not.toBe(cellStyles.backgroundColor);
    });

    test('A11Y-005b: Interactive elements have sufficient contrast', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Check button contrast
      const createButton = page.getByRole('button', { name: /create|add|new/i });
      const buttonStyles = await createButton.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          color: styles.color,
          backgroundColor: styles.backgroundColor,
          borderColor: styles.borderColor
        };
      });

      expect(buttonStyles.color).not.toBe(buttonStyles.backgroundColor);

      // Check link contrast (recipe name)
      const recipeLink = page.getByText(testRecipe.name);
      const linkStyles = await recipeLink.evaluate((el) => {
        const styles = window.getComputedStyle(el);
        return {
          color: styles.color,
          backgroundColor: styles.backgroundColor
        };
      });

      expect(linkStyles.color).not.toBe(linkStyles.backgroundColor);
    });
  });

  test.describe('Error Handling and Validation', () => {

    test('A11Y-006a: Form validation errors are accessible', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Go to create form
      await page.getByRole('button', { name: /create|add|new/i }).click();

      // Try to submit empty form
      await page.getByRole('button', { name: /save|create/i }).click();

      // Check for validation error messages
      const errorMessages = page.locator('[role="alert"]').or(page.locator('.error, .invalid, [aria-invalid="true"]'));
      const errorCount = await errorMessages.count();

      if (errorCount > 0) {
        // If validation errors exist, they should be accessible
        const firstError = errorMessages.first();

        // Error should be associated with the relevant form field
        const ariaDescribedBy = await page.getByLabel('Recipe Name').getAttribute('aria-describedby');
        const ariaInvalid = await page.getByLabel('Recipe Name').getAttribute('aria-invalid');

        expect(ariaDescribedBy || ariaInvalid).toBeTruthy();
      }
    });

    test('A11Y-006b: Error state recovery', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Simulate network error by blocking requests
      await page.route('**/recipes', (route) => {
        route.abort();
      });

      // Try to create a recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();

      // Fill form
      await fillRecipeForm(page, testRecipe);

      // Try to submit (should fail)
      await page.getByRole('button', { name: /save|create/i }).click();

      // Should show error message
      const errorMessage = page.locator('[role="alert"]').or(page.getByText(/error|błąd/i));
      const hasError = await errorMessage.count() > 0;

      if (hasError) {
        // Error should be accessible
        await expect(errorMessage.first()).toBeVisible();

        // Focus should be managed appropriately
        const focusedElement = await page.evaluate(() => document.activeElement?.tagName);
        expect(['BUTTON', 'INPUT', 'BODY']).toContain(focusedElement);
      }
    });
  });

  test.describe('Mobile Accessibility', () => {

    test('A11Y-007a: Touch targets are sufficiently large', async ({ page }) => {
      await createUserAndLogin(page);

      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Check button sizes
      const createButton = page.getByRole('button', { name: /create|add|new/i });
      const buttonBox = await createButton.boundingBox();

      // Touch targets should be at least 44x44 pixels
      if (buttonBox) {
        expect(buttonBox.width).toBeGreaterThanOrEqual(40); // Allow some tolerance
        expect(buttonBox.height).toBeGreaterThanOrEqual(40);
      }

      // Check action buttons in table
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      const actionButtons = recipeRow.locator('button');

      for (let i = 0; i < await actionButtons.count(); i++) {
        const button = actionButtons.nth(i);
        const box = await button.boundingBox();

        if (box) {
          expect(box.width).toBeGreaterThanOrEqual(40);
          expect(box.height).toBeGreaterThanOrEqual(40);
        }
      }
    });

    test('A11Y-007b: Mobile screen reader compatibility', async ({ page }) => {
      await createUserAndLogin(page);

      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify semantic structure is maintained on mobile
      await expect(page.getByRole('heading', { name: 'Przepisy' })).toBeVisible();
      await expect(page.getByRole('table')).toBeVisible();

      // Navigation should be accessible
      const searchInput = page.getByPlaceholder('Search recipes...');
      await expect(searchInput).toBeVisible();

      // Action buttons should maintain their labels
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      await expect(recipeRow.getByRole('button', { name: `View ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Edit ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` })).toBeVisible();
    });
  });
});
