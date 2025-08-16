import { test, expect } from '@playwright/test';

// E2E tests for Recipe Components (Toolbar, Table, Modals)
// Testing specific component behaviors and interactions

const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-comp-${random}@example.com`;
};

const createUserAndLogin = async (page) => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E Component User');
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

const testRecipes = [
  {
    name: 'Component Test Breakfast',
    category: 'breakfast',
    calories: 300,
    servings: 2,
    instructions: 'Simple breakfast recipe for testing.',
    ingredients: [
      { name: 'Bread', quantity: 2, unit: 'pieces' },
      { name: 'Butter', quantity: 10, unit: 'g' }
    ]
  },
  {
    name: 'Component Test Dinner',
    category: 'dinner',
    calories: 500,
    servings: 4,
    instructions: 'Hearty dinner recipe for testing.',
    ingredients: [
      { name: 'Rice', quantity: 200, unit: 'g' },
      { name: 'Chicken', quantity: 300, unit: 'g' }
    ]
  },
  {
    name: 'Component Test Supper',
    category: 'supper',
    calories: 250,
    servings: 2,
    instructions: 'Light supper recipe for testing.',
    ingredients: [
      { name: 'Salad', quantity: 150, unit: 'g' },
      { name: 'Dressing', quantity: 30, unit: 'ml' }
    ]
  }
];

test.describe('Recipe Components E2E Tests', () => {

  test.describe('RecipesToolbar Component', () => {

    test('COMP-001a: Search input works with debouncing', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create test recipes
      for (const recipe of testRecipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test search input behavior
      const searchInput = page.getByPlaceholder('Search recipes...');

      // Verify input is visible and accessible
      await expect(searchInput).toBeVisible();
      await expect(searchInput).toBeEditable();

      // Test typing behavior (should not trigger immediate search)
      await searchInput.fill('Comp');
      // Should not filter immediately (debounced)
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();

      // Wait for debounce and verify filtering
      await page.waitForTimeout(400);
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).toBeVisible();

      // Complete search term
      await searchInput.fill('Component Test Breakfast');
      await page.waitForTimeout(400);

      // Should show only breakfast recipe
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).not.toBeVisible();
      await expect(page.getByText('Component Test Supper')).not.toBeVisible();
    });

    test('COMP-001b: Category dropdown filters correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create test recipes
      for (const recipe of testRecipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test category dropdown
      const categorySelect = page.locator('select').or(page.getByRole('combobox', { name: /category/i }));

      // Verify dropdown is present and has correct options
      await expect(categorySelect).toBeVisible();

      // Test "All Categories" option
      await categorySelect.selectOption('all');
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).toBeVisible();
      await expect(page.getByText('Component Test Supper')).toBeVisible();

      // Test breakfast filter
      await categorySelect.selectOption('breakfast');
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).not.toBeVisible();
      await expect(page.getByText('Component Test Supper')).not.toBeVisible();

      // Test dinner filter
      await categorySelect.selectOption('dinner');
      await expect(page.getByText('Component Test Breakfast')).not.toBeVisible();
      await expect(page.getByText('Component Test Dinner')).toBeVisible();
      await expect(page.getByText('Component Test Supper')).not.toBeVisible();

      // Test supper filter
      await categorySelect.selectOption('supper');
      await expect(page.getByText('Component Test Breakfast')).not.toBeVisible();
      await expect(page.getByText('Component Test Dinner')).not.toBeVisible();
      await expect(page.getByText('Component Test Supper')).toBeVisible();
    });

    test('COMP-001c: Reset filters button works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create test recipes
      for (const recipe of testRecipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      const searchInput = page.getByPlaceholder('Search recipes...');
      const categorySelect = page.locator('select').or(page.getByRole('combobox', { name: /category/i }));
      const resetButton = page.getByRole('button', { name: /reset|clear/i });

      // Apply filters
      await searchInput.fill('breakfast');
      await categorySelect.selectOption('breakfast');
      await page.waitForTimeout(400);

      // Verify filters are applied
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).not.toBeVisible();

      // Reset filters should be enabled when filters are active
      await expect(resetButton).toBeEnabled();

      // Click reset
      await resetButton.click();

      // Verify filters are cleared
      await expect(searchInput).toHaveValue('');
      await expect(categorySelect).toHaveValue('all');

      // All recipes should be visible again
      await expect(page.getByText('Component Test Breakfast')).toBeVisible();
      await expect(page.getByText('Component Test Dinner')).toBeVisible();
      await expect(page.getByText('Component Test Supper')).toBeVisible();

      // Reset button should be disabled when no filters are active
      await expect(resetButton).toBeDisabled();
    });

    test('COMP-001d: Create recipe button navigation', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Find and click create button
      const createButton = page.getByRole('button', { name: /create|add|new/i });
      await expect(createButton).toBeVisible();
      await expect(createButton).toBeEnabled();

      await createButton.click();

      // Should navigate to create form
      await expect(page).toHaveURL(/recipes\/create$/);

      // Verify form is loaded
      await expect(page.getByLabel('Recipe Name')).toBeVisible();
      await expect(page.getByLabel('Category')).toBeVisible();
    });
  });

  test.describe('RecipesTable Component', () => {

    test('COMP-002a: Table displays recipe data correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify table structure
      await expect(page.getByRole('table')).toBeVisible();

      // Verify headers
      await expect(page.getByRole('columnheader', { name: /name/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /category/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /calories/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /servings/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /created/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /actions/i })).toBeVisible();

      // Verify recipe data in table
      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });
      await expect(recipeRow).toBeVisible();

      // Check each column data
      await expect(recipeRow.getByText(testRecipe.name)).toBeVisible();
      await expect(recipeRow.getByText(testRecipe.category)).toBeVisible();
      await expect(recipeRow.getByText(`${testRecipe.calories} kcal`)).toBeVisible();
      await expect(recipeRow.getByText(testRecipe.servings.toString())).toBeVisible();

      // Check action buttons
      await expect(recipeRow.getByRole('button', { name: `View ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Edit ${testRecipe.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` })).toBeVisible();
    });

    test('COMP-002b: Sortable headers work correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create recipes with different values for sorting
      const recipesForSorting = [
        { ...testRecipes[0], name: 'A Recipe', calories: 100 },
        { ...testRecipes[1], name: 'Z Recipe', calories: 500 },
        { ...testRecipes[2], name: 'M Recipe', calories: 300 }
      ];

      for (const recipe of recipesForSorting) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test name sorting
      const nameHeader = page.getByRole('columnheader', { name: /name/i });

      // Click to sort ascending
      await nameHeader.click();
      await page.waitForTimeout(500);

      // Check order (A, M, Z)
      const nameButtons = page.locator('tbody tr td:first-child button');
      const firstRecipe = await nameButtons.first().textContent();
      const lastRecipe = await nameButtons.last().textContent();

      expect(firstRecipe).toBe('A Recipe');
      expect(lastRecipe).toBe('Z Recipe');

      // Click again to sort descending
      await nameHeader.click();
      await page.waitForTimeout(500);

      // Check reverse order (Z, M, A)
      const firstReversed = await nameButtons.first().textContent();
      const lastReversed = await nameButtons.last().textContent();

      expect(firstReversed).toBe('Z Recipe');
      expect(lastReversed).toBe('A Recipe');

      // Test calories sorting
      const caloriesHeader = page.getByRole('columnheader', { name: /calories/i });
      await caloriesHeader.click();
      await page.waitForTimeout(500);

      // Check calories are sorted (100, 300, 500)
      const calorieCells = page.locator('tbody tr td:nth-child(3)');
      const firstCalories = await calorieCells.first().textContent();
      const lastCalories = await calorieCells.last().textContent();

      expect(firstCalories).toContain('100');
      expect(lastCalories).toContain('500');
    });

    test('COMP-002c: Recipe row actions work correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Test view action
      await recipeRow.getByRole('button', { name: `View ${testRecipe.name}` }).click();
      await expect(page).toHaveURL(/recipes\/\d+$/);
      await expect(page.getByRole('heading', { name: testRecipe.name })).toBeVisible();

      // Go back to list
      await page.goto('/recipes');

      // Test edit action
      await recipeRow.getByRole('button', { name: `Edit ${testRecipe.name}` }).click();
      await expect(page).toHaveURL(/recipes\/\d+\/edit$/);
      await expect(page.getByLabel('Recipe Name')).toHaveValue(testRecipe.name);

      // Go back to list
      await page.goto('/recipes');

      // Test delete action (opens modal)
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Cancel to close modal
      await page.getByRole('button', { name: 'Cancel' }).click();
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();
    });

    test('COMP-002d: Empty state display', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Verify empty state when no recipes
      await expect(page.getByText('Brak przepisów')).toBeVisible();
      await expect(page.getByText('Rozpocznij od dodania pierwszego przepisu')).toBeVisible();

      // Create a recipe then search for non-existent recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipes[0]);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Search for non-existent recipe
      await page.getByPlaceholder('Search recipes...').fill('nonexistent');
      await page.waitForTimeout(500);

      // Should show "No recipes found" message
      await expect(page.getByText('No recipes found')).toBeVisible();
    });
  });

  test.describe('ConfirmDeleteModal Component', () => {

    test('COMP-003a: Delete modal appears and functions correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Click delete button to open modal
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();

      // Verify modal content
      await expect(page.getByText('Delete Recipe')).toBeVisible();
      await expect(page.getByText(`Are you sure you want to delete "${testRecipe.name}"`)).toBeVisible();
      await expect(page.getByText('This action cannot be undone')).toBeVisible();

      // Verify modal buttons
      await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Delete Recipe' })).toBeVisible();

      // Verify modal has proper ARIA attributes
      const modal = page.locator('[role="dialog"]').or(page.locator('[role="alertdialog"]'));
      await expect(modal).toBeVisible();
    });

    test('COMP-003b: Modal cancel functionality', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Open modal
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Click cancel
      await page.getByRole('button', { name: 'Cancel' }).click();

      // Modal should close
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();

      // Recipe should still exist
      await expect(page.getByText(testRecipe.name)).toBeVisible();
    });

    test('COMP-003c: Modal backdrop click closes modal', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Open modal
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Click backdrop (outside modal content)
      await page.locator('.fixed.inset-0').click({ position: { x: 10, y: 10 } });

      // Modal should close
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();

      // Recipe should still exist
      await expect(page.getByText(testRecipe.name)).toBeVisible();
    });

    test('COMP-003d: Modal confirm deletion works', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Open modal
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Click confirm delete
      await page.getByRole('button', { name: 'Delete Recipe' }).click();

      // Modal should close
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();

      // Recipe should be removed from list
      await expect(page.getByText(testRecipe.name)).not.toBeVisible();

      // Should show empty state or remaining recipes
      const hasOtherRecipes = await page.locator('tbody tr').count() > 0;
      if (!hasOtherRecipes) {
        await expect(page.getByText('Brak przepisów')).toBeVisible();
      }
    });

    test('COMP-003e: Modal keyboard navigation', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create a test recipe
      const testRecipe = testRecipes[0];
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipe);
      await page.getByRole('button', { name: /save|create/i }).click();

      const recipeRow = page.locator('tr').filter({ hasText: testRecipe.name });

      // Open modal
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Test Escape key closes modal
      await page.keyboard.press('Escape');
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();

      // Recipe should still exist
      await expect(page.getByText(testRecipe.name)).toBeVisible();

      // Open modal again
      await recipeRow.getByRole('button', { name: `Delete ${testRecipe.name}` }).click();
      await expect(page.getByText('Delete Recipe')).toBeVisible();

      // Test Tab navigation between buttons
      await page.keyboard.press('Tab');
      const focusedElement = await page.evaluate(() => document.activeElement?.textContent);
      expect(focusedElement).toMatch(/(Cancel|Delete Recipe)/);
    });
  });

  test.describe('Pagination Component', () => {

    test('COMP-004a: Pagination component displays correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create enough recipes for pagination (16+ recipes for 15 per page)
      for (let i = 1; i <= 18; i++) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        const recipe = {
          name: `Pagination Test Recipe ${i.toString().padStart(2, '0')}`,
          category: testRecipes[i % testRecipes.length].category,
          calories: 200 + i * 10,
          servings: 2,
          instructions: `Test recipe ${i} instructions.`,
          ingredients: [{ name: `Ingredient ${i}`, quantity: i, unit: 'g' }]
        };
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Verify pagination appears
      const pagination = page.locator('[data-testid="pagination"]').or(page.getByRole('navigation', { name: /pagination/i }));
      await expect(pagination).toBeVisible();

      // Verify page numbers
      await expect(page.getByRole('button', { name: '1' })).toBeVisible();
      await expect(page.getByRole('button', { name: '2' })).toBeVisible();

      // Verify navigation buttons
      const nextButton = page.getByRole('button', { name: /next/i });
      const prevButton = page.getByRole('button', { name: /previous|prev/i });

      await expect(nextButton).toBeVisible();
      await expect(prevButton).toBeVisible();

      // First page: previous should be disabled, next enabled
      await expect(prevButton).toBeDisabled();
      await expect(nextButton).toBeEnabled();
    });

    test('COMP-004b: Pagination navigation works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create recipes for pagination
      for (let i = 1; i <= 18; i++) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        const recipe = {
          name: `Page Test Recipe ${i.toString().padStart(2, '0')}`,
          category: testRecipes[i % testRecipes.length].category,
          calories: 200 + i * 10,
          servings: 2,
          instructions: `Test recipe ${i} instructions.`,
          ingredients: [{ name: `Ingredient ${i}`, quantity: i, unit: 'g' }]
        };
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test next page navigation
      const nextButton = page.getByRole('button', { name: /next/i });
      await nextButton.click();

      // Should be on page 2
      await expect(page).toHaveURL(/[?&]page=2/);

      // Verify different recipes are shown
      await expect(page.getByText('Page Test Recipe 16')).toBeVisible();
      await expect(page.getByText('Page Test Recipe 01')).not.toBeVisible();

      // Test previous page navigation
      const prevButton = page.getByRole('button', { name: /previous|prev/i });
      await prevButton.click();

      // Should be back on page 1
      await expect(page).toHaveURL(/recipes(?!\?|\?(?!.*page=))/);
      await expect(page.getByText('Page Test Recipe 01')).toBeVisible();
      await expect(page.getByText('Page Test Recipe 16')).not.toBeVisible();

      // Test direct page number click
      await page.getByRole('button', { name: '2' }).click();
      await expect(page).toHaveURL(/[?&]page=2/);
    });

    test('COMP-004c: Pagination maintains filters', async ({ page }) => {
      await createUserAndLogin(page);
      await page.goto('/recipes');

      // Create recipes in different categories for pagination
      for (let i = 1; i <= 20; i++) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        const recipe = {
          name: `Filter Page Test ${i.toString().padStart(2, '0')}`,
          category: 'breakfast', // All breakfast to test filtering
          calories: 200 + i * 10,
          servings: 2,
          instructions: `Test recipe ${i} instructions.`,
          ingredients: [{ name: `Ingredient ${i}`, quantity: i, unit: 'g' }]
        };
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Apply a search filter
      await page.getByPlaceholder('Search recipes...').fill('Filter Page');
      await page.waitForTimeout(500);

      // Navigate to page 2
      const nextButton = page.getByRole('button', { name: /next/i });
      await nextButton.click();

      // URL should maintain search parameter
      await expect(page).toHaveURL(/[?&]search=Filter\+Page/);
      await expect(page).toHaveURL(/[?&]page=2/);

      // Search filter should still be active
      await expect(page.getByPlaceholder('Search recipes...')).toHaveValue('Filter Page');

      // Should show filtered results on page 2
      await expect(page.getByText('Filter Page Test 16')).toBeVisible();
    });
  });

  test.describe('Responsive Design', () => {

    test('COMP-005a: Mobile responsiveness', async ({ page }) => {
      await createUserAndLogin(page);

      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipes[0]);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify mobile layout
      await expect(page.getByRole('heading', { name: 'Przepisy' })).toBeVisible();

      // Toolbar should be responsive
      const searchInput = page.getByPlaceholder('Search recipes...');
      await expect(searchInput).toBeVisible();

      // Table should be visible (may scroll horizontally)
      await expect(page.getByRole('table')).toBeVisible();

      // Action buttons should be accessible
      const recipeRow = page.locator('tr').filter({ hasText: testRecipes[0].name });
      await expect(recipeRow.getByRole('button', { name: /view/i })).toBeVisible();
    });

    test('COMP-005b: Tablet responsiveness', async ({ page }) => {
      await createUserAndLogin(page);

      // Set tablet viewport
      await page.setViewportSize({ width: 768, height: 1024 });

      await page.goto('/recipes');

      // Create a test recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipes[0]);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify tablet layout
      await expect(page.getByRole('heading', { name: 'Przepisy' })).toBeVisible();

      // Toolbar should be well arranged
      const toolbar = page.locator('.flex').filter({ hasText: /search|category/i }).first();
      await expect(toolbar).toBeVisible();

      // Table should display properly
      await expect(page.getByRole('table')).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /name/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /actions/i })).toBeVisible();
    });
  });
});
