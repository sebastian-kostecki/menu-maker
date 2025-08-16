import { test, expect } from '@playwright/test';

// E2E-001: Recipe CRUD Flow Test
// Test ID: REC-001 to REC-005 from test plan
const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-recipes-${random}@example.com`;
};

// Test data for recipes
const testRecipeData = {
  breakfast: {
    name: 'E2E Test Pancakes',
    category: 'breakfast',
    calories: 350,
    servings: 4,
    instructions: 'Mix ingredients and cook on griddle until golden brown.',
    ingredients: [
      { name: 'Flour', quantity: 2, unit: 'cups' },
      { name: 'Eggs', quantity: 2, unit: 'pieces' },
      { name: 'Milk', quantity: 1.5, unit: 'cups' }
    ]
  },
  dinner: {
    name: 'E2E Test Pasta',
    category: 'dinner',
    calories: 450,
    servings: 3,
    instructions: 'Boil pasta, prepare sauce, and combine.',
    ingredients: [
      { name: 'Pasta', quantity: 300, unit: 'g' },
      { name: 'Tomato Sauce', quantity: 400, unit: 'ml' },
      { name: 'Cheese', quantity: 100, unit: 'g' }
    ]
  }
};

// Test helper functions
const createUserAndLogin = async (page) => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  // Register user
  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E Recipe User');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');

  return { email, password };
};

const navigateToRecipes = async (page) => {
  await page.goto('/recipes');
  await expect(page).toHaveURL(/recipes$/);
  await expect(page.getByRole('heading', { name: 'Przepisy' })).toBeVisible();
};

const fillRecipeForm = async (page, recipeData) => {
  await page.getByLabel('Recipe Name').fill(recipeData.name);
  await page.getByLabel('Category').selectOption(recipeData.category);
  await page.getByLabel('Calories').fill(recipeData.calories.toString());
  await page.getByLabel('Servings').fill(recipeData.servings.toString());
  await page.getByLabel('Instructions').fill(recipeData.instructions);

  // Add ingredients
  for (const ingredient of recipeData.ingredients) {
    await page.getByRole('button', { name: 'Add Ingredient' }).click();
    await page.getByLabel('Ingredient Name').last().fill(ingredient.name);
    await page.getByLabel('Quantity').last().fill(ingredient.quantity.toString());
    await page.getByLabel('Unit').last().selectOption(ingredient.unit);
  }
};

// Test suite for Recipe functionality
test.describe('Recipes Management', () => {

  test.describe('Navigation and Access Control', () => {

    test('E2E-001a: Unauthenticated user is redirected to login', async ({ page }) => {
      await page.goto('/recipes');
      await expect(page).toHaveURL(/login/);
    });

    test('E2E-001b: Authenticated user can access recipes page', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Verify page content
      await expect(page.getByText('Zarządzaj swoją kolekcją przepisów')).toBeVisible();
      await expect(page.getByPlaceholder('Search recipes...')).toBeVisible();
      await expect(page.getByText('All Categories')).toBeVisible();
    });

    test('E2E-001c: Navigation menu contains recipes link', async ({ page }) => {
      await createUserAndLogin(page);

      // Check if recipes link is in navigation
      const recipesLink = page.getByRole('link', { name: /recipes|przepisy/i });
      await expect(recipesLink).toBeVisible();

      await recipesLink.click();
      await expect(page).toHaveURL(/recipes$/);
    });
  });

  test.describe('Recipe CRUD Operations', () => {

    test('E2E-002a: User can create a new recipe', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Click create button
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await expect(page).toHaveURL(/recipes\/create$/);

      // Fill form
      await fillRecipeForm(page, testRecipeData.breakfast);

      // Submit form
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify redirect to recipes list
      await expect(page).toHaveURL(/recipes$/);

      // Verify recipe appears in list
      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();
      await expect(page.getByText('breakfast')).toBeVisible();
    });

    test('E2E-002b: User can view recipe details', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe first
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.dinner);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Click on recipe name to view details
      await page.getByText(testRecipeData.dinner.name).click();
      await expect(page).toHaveURL(/recipes\/\d+$/);

      // Verify recipe details are displayed
      await expect(page.getByRole('heading', { name: testRecipeData.dinner.name })).toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.instructions)).toBeVisible();
      await expect(page.getByText(`${testRecipeData.dinner.calories} kcal`)).toBeVisible();
      await expect(page.getByText(`${testRecipeData.dinner.servings} servings`)).toBeVisible();

      // Verify ingredients are listed
      for (const ingredient of testRecipeData.dinner.ingredients) {
        await expect(page.getByText(ingredient.name)).toBeVisible();
      }
    });

    test('E2E-002c: User can edit existing recipe', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe first
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Click edit button for the recipe
      const recipeRow = page.locator('tr').filter({ hasText: testRecipeData.breakfast.name });
      await recipeRow.getByRole('button', { name: /edit/i }).click();
      await expect(page).toHaveURL(/recipes\/\d+\/edit$/);

      // Modify recipe
      const updatedName = testRecipeData.breakfast.name + ' Updated';
      await page.getByLabel('Recipe Name').fill(updatedName);
      await page.getByLabel('Calories').fill('400');

      // Save changes
      await page.getByRole('button', { name: /save|update/i }).click();
      await expect(page).toHaveURL(/recipes$/);

      // Verify changes are reflected
      await expect(page.getByText(updatedName)).toBeVisible();
    });

    test('E2E-002d: User can delete recipe with confirmation', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe first
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Click delete button
      const recipeRow = page.locator('tr').filter({ hasText: testRecipeData.breakfast.name });
      await recipeRow.getByRole('button', { name: /delete/i }).click();

      // Verify confirmation modal appears
      await expect(page.getByText('Delete Recipe')).toBeVisible();
      await expect(page.getByText(`Are you sure you want to delete "${testRecipeData.breakfast.name}"`)).toBeVisible();

      // Cancel first to test modal
      await page.getByRole('button', { name: 'Cancel' }).click();
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();

      // Recipe should still be visible
      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();

      // Delete for real
      await recipeRow.getByRole('button', { name: /delete/i }).click();
      await page.getByRole('button', { name: 'Delete Recipe' }).click();

      // Verify recipe is removed
      await expect(page.getByText(testRecipeData.breakfast.name)).not.toBeVisible();
    });
  });

  test.describe('Search and Filtering', () => {

    test('E2E-003a: Search functionality works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create multiple recipes
      const recipes = [testRecipeData.breakfast, testRecipeData.dinner];

      for (const recipe of recipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test search functionality
      const searchInput = page.getByPlaceholder('Search recipes...');

      // Search for breakfast recipe
      await searchInput.fill('Pancakes');
      await page.waitForTimeout(500); // Wait for debounce

      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.name)).not.toBeVisible();

      // Clear search
      await searchInput.clear();
      await page.waitForTimeout(500);

      // Both recipes should be visible again
      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.name)).toBeVisible();
    });

    test('E2E-003b: Category filtering works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create recipes in different categories
      const recipes = [testRecipeData.breakfast, testRecipeData.dinner];

      for (const recipe of recipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Filter by breakfast category
      await page.getByRole('combobox', { name: /category/i }).selectOption('breakfast');

      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.name)).not.toBeVisible();

      // Filter by dinner category
      await page.getByRole('combobox', { name: /category/i }).selectOption('dinner');

      await expect(page.getByText(testRecipeData.breakfast.name)).not.toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.name)).toBeVisible();

      // Reset to all categories
      await page.getByRole('combobox', { name: /category/i }).selectOption('all');

      await expect(page.getByText(testRecipeData.breakfast.name)).toBeVisible();
      await expect(page.getByText(testRecipeData.dinner.name)).toBeVisible();
    });

    test('E2E-003c: Reset filters functionality', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create multiple recipes
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Apply filters
      await page.getByPlaceholder('Search recipes...').fill('test');
      await page.getByRole('combobox', { name: /category/i }).selectOption('breakfast');

      // Reset filters
      await page.getByRole('button', { name: /reset|clear/i }).click();

      // Verify filters are cleared
      await expect(page.getByPlaceholder('Search recipes...')).toHaveValue('');
      await expect(page.getByRole('combobox', { name: /category/i })).toHaveValue('all');
    });
  });

  test.describe('Sorting and Pagination', () => {

    test('E2E-004a: Column sorting works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create multiple recipes with different properties
      const recipes = [
        { ...testRecipeData.breakfast, name: 'A Recipe', calories: 300 },
        { ...testRecipeData.dinner, name: 'Z Recipe', calories: 500 }
      ];

      for (const recipe of recipes) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Test name sorting
      const nameHeader = page.getByRole('columnheader', { name: /name/i });
      await nameHeader.click();

      // Get all recipe names in order
      const recipeNames = await page.locator('tbody tr td:first-child button').allTextContents();
      expect(recipeNames[0]).toBe('A Recipe');
      expect(recipeNames[1]).toBe('Z Recipe');

      // Click again to reverse sort
      await nameHeader.click();
      const reversedNames = await page.locator('tbody tr td:first-child button').allTextContents();
      expect(reversedNames[0]).toBe('Z Recipe');
      expect(reversedNames[1]).toBe('A Recipe');
    });

    test('E2E-004b: Pagination works with multiple pages', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create enough recipes to trigger pagination (assuming 15 per page)
      for (let i = 1; i <= 18; i++) {
        await page.getByRole('button', { name: /create|add|new/i }).click();
        const recipe = {
          ...testRecipeData.breakfast,
          name: `Recipe ${i.toString().padStart(2, '0')}`
        };
        await fillRecipeForm(page, recipe);
        await page.getByRole('button', { name: /save|create/i }).click();
      }

      // Verify pagination controls appear
      const pagination = page.locator('[data-testid="pagination"]').or(page.getByRole('navigation', { name: /pagination/i }));
      await expect(pagination).toBeVisible();

      // Verify first page shows 15 recipes
      const recipeRows = page.locator('tbody tr').filter({ hasText: /Recipe \d+/ });
      await expect(recipeRows).toHaveCount(15);

      // Navigate to second page
      await page.getByRole('button', { name: /next|2/i }).click();

      // Verify second page shows remaining recipes
      await expect(recipeRows).toHaveCount(3);

      // Verify page 2 URL parameter
      await expect(page).toHaveURL(/[?&]page=2/);
    });
  });

  test.describe('User Isolation and Authorization', () => {

    test('E2E-005a: Users can only see their own recipes', async ({ page, browser }) => {
      // Create first user and recipe
      const user1 = await createUserAndLogin(page);
      await navigateToRecipes(page);

      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, { ...testRecipeData.breakfast, name: 'User 1 Recipe' });
      await page.getByRole('button', { name: /save|create/i }).click();

      await expect(page.getByText('User 1 Recipe')).toBeVisible();

      // Create second user in new context
      const context2 = await browser.newContext();
      const page2 = await context2.newPage();

      const user2 = await createUserAndLogin(page2);
      await navigateToRecipes(page2);

      // User 2 should not see User 1's recipe
      await expect(page2.getByText('User 1 Recipe')).not.toBeVisible();

      // Create recipe for User 2
      await page2.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page2, { ...testRecipeData.dinner, name: 'User 2 Recipe' });
      await page2.getByRole('button', { name: /save|create/i }).click();

      await expect(page2.getByText('User 2 Recipe')).toBeVisible();

      // Verify User 1 still doesn't see User 2's recipe
      await page.reload();
      await expect(page.getByText('User 1 Recipe')).toBeVisible();
      await expect(page.getByText('User 2 Recipe')).not.toBeVisible();

      await context2.close();
    });

    test('E2E-005b: User cannot access other users recipes directly', async ({ page, browser }) => {
      // Create first user and recipe
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Get recipe URL
      await page.getByText(testRecipeData.breakfast.name).click();
      const recipeUrl = page.url();
      const recipeId = recipeUrl.match(/recipes\/(\d+)/)?.[1];

      // Create second user
      const context2 = await browser.newContext();
      const page2 = await context2.newPage();
      await createUserAndLogin(page2);

      // Try to access first user's recipe directly
      await page2.goto(`/recipes/${recipeId}`);

      // Should be redirected or show 404/403
      await expect(page2).not.toHaveURL(recipeUrl);
      // May show 404, 403, or redirect to recipes list
      const isAccessDenied = await page2.getByText(/not found|forbidden|access denied/i).count() > 0;
      const isRedirected = page2.url().includes('/recipes') && !page2.url().includes(`/recipes/${recipeId}`);

      expect(isAccessDenied || isRedirected).toBeTruthy();

      await context2.close();
    });
  });

  test.describe('Empty States and Error Handling', () => {

    test('E2E-006a: Empty state is displayed when no recipes exist', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Verify empty state message
      await expect(page.getByText('Brak przepisów')).toBeVisible();
      await expect(page.getByText('Rozpocznij od dodania pierwszego przepisu')).toBeVisible();

      // Verify recipe count shows 0
      await expect(page.getByText('(0 przepisów)')).toBeVisible();
    });

    test('E2E-006b: No results state when search yields no matches', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create one recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Search for non-existent recipe
      await page.getByPlaceholder('Search recipes...').fill('nonexistent recipe');
      await page.waitForTimeout(500);

      // Verify no results message
      await expect(page.getByText('No recipes found')).toBeVisible();
    });

    test('E2E-006c: Form validation prevents invalid recipe creation', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      await page.getByRole('button', { name: /create|add|new/i }).click();

      // Try to submit empty form
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify validation errors appear
      await expect(page.getByText(/name.*required/i)).toBeVisible();
      await expect(page.getByText(/category.*required/i)).toBeVisible();

      // Form should not submit
      await expect(page).toHaveURL(/recipes\/create$/);
    });
  });

  test.describe('Accessibility and UX', () => {

    test('E2E-007a: Keyboard navigation works correctly', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe for testing
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Test tab navigation through search and filters
      await page.getByPlaceholder('Search recipes...').focus();
      await page.keyboard.press('Tab');
      await expect(page.getByRole('combobox', { name: /category/i })).toBeFocused();

      // Test Enter key on search
      await page.getByPlaceholder('Search recipes...').focus();
      await page.getByPlaceholder('Search recipes...').fill('test');
      await page.keyboard.press('Enter');

      // Should trigger search (URL should update or results should filter)
      await page.waitForTimeout(500);
    });

    test('E2E-007b: ARIA labels and roles are present', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe for testing
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Verify table structure has proper roles
      await expect(page.getByRole('table')).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /name/i })).toBeVisible();
      await expect(page.getByRole('columnheader', { name: /category/i })).toBeVisible();

      // Verify action buttons have aria-labels
      const recipeRow = page.locator('tr').filter({ hasText: testRecipeData.breakfast.name });
      await expect(recipeRow.getByRole('button', { name: `View ${testRecipeData.breakfast.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Edit ${testRecipeData.breakfast.name}` })).toBeVisible();
      await expect(recipeRow.getByRole('button', { name: `Delete ${testRecipeData.breakfast.name}` })).toBeVisible();
    });

    test('E2E-007c: Focus management in delete modal', async ({ page }) => {
      await createUserAndLogin(page);
      await navigateToRecipes(page);

      // Create a recipe
      await page.getByRole('button', { name: /create|add|new/i }).click();
      await fillRecipeForm(page, testRecipeData.breakfast);
      await page.getByRole('button', { name: /save|create/i }).click();

      // Open delete modal
      const recipeRow = page.locator('tr').filter({ hasText: testRecipeData.breakfast.name });
      await recipeRow.getByRole('button', { name: /delete/i }).click();

      // Focus should be managed in modal
      await expect(page.getByRole('dialog').or(page.locator('[role="alertdialog"]'))).toBeVisible();

      // Test Escape key closes modal
      await page.keyboard.press('Escape');
      await expect(page.getByText('Delete Recipe')).not.toBeVisible();
    });
  });
});
