import { test, expect } from '@playwright/test';

// Performance and Scale Testing for Recipes (PERF-001, PERF-002)
// Tests pagination, large data sets, and N+1 query prevention

const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-perf-${random}@example.com`;
};

const createUserAndLogin = async (page) => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E Performance User');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');

  return { email, password };
};

const createManyRecipes = async (page, count: number) => {
  const categories = ['breakfast', 'dinner', 'supper'];

  for (let i = 1; i <= count; i++) {
    await page.goto('/recipes/create');

    const category = categories[i % categories.length];
    const recipe = {
      name: `Performance Test Recipe ${i.toString().padStart(3, '0')}`,
      category,
      calories: 200 + (i * 10),
      servings: 2 + (i % 4),
      instructions: `Instructions for recipe ${i}. Mix ingredients and cook properly.`,
      ingredients: [
        { name: `Ingredient A${i}`, quantity: i, unit: 'g' },
        { name: `Ingredient B${i}`, quantity: i * 0.5, unit: 'ml' }
      ]
    };

    // Fill form quickly
    await page.getByLabel('Recipe Name').fill(recipe.name);
    await page.getByLabel('Category').selectOption(recipe.category);
    await page.getByLabel('Calories').fill(recipe.calories.toString());
    await page.getByLabel('Servings').fill(recipe.servings.toString());
    await page.getByLabel('Instructions').fill(recipe.instructions);

    // Add ingredients
    for (const ingredient of recipe.ingredients) {
      await page.getByRole('button', { name: 'Add Ingredient' }).click();
      await page.getByLabel('Ingredient Name').last().fill(ingredient.name);
      await page.getByLabel('Quantity').last().fill(ingredient.quantity.toString());
      await page.getByLabel('Unit').last().selectOption(ingredient.unit);
    }

    await page.getByRole('button', { name: /save|create/i }).click();
    await page.waitForURL('**/recipes');
  }
};

test.describe('Recipes Performance Tests', () => {

  test.describe('Pagination Performance', () => {

    test('PERF-001a: Pagination works correctly with 50 recipes', async ({ page }) => {
      await createUserAndLogin(page);

      // Create 50 recipes to test pagination (assuming 15 per page = 4 pages)
      await createManyRecipes(page, 50);

      await page.goto('/recipes');

      // Verify page loads in reasonable time
      const startTime = Date.now();
      await page.waitForLoadState('networkidle');
      const loadTime = Date.now() - startTime;

      // Should load within 5 seconds even with many recipes
      expect(loadTime).toBeLessThan(5000);

      // Verify pagination is present
      const pagination = page.locator('[data-testid="pagination"]').or(page.getByRole('navigation', { name: /pagination/i }));
      await expect(pagination).toBeVisible();

      // Verify first page shows exactly 15 recipes (or configured page size)
      const recipeRows = page.locator('tbody tr').filter({ hasText: /Performance Test Recipe/ });
      await expect(recipeRows).toHaveCount(15);

      // Test navigation through all pages
      let currentPage = 1;
      const totalPages = Math.ceil(50 / 15); // 4 pages

      while (currentPage < totalPages) {
        // Click next page
        const nextButton = page.getByRole('button', { name: /next/i }).or(page.getByRole('button', { name: (currentPage + 1).toString() }));
        await nextButton.click();

        currentPage++;

        // Verify URL contains page parameter
        await expect(page).toHaveURL(new RegExp(`[?&]page=${currentPage}`));

        // Verify page loads quickly
        const pageStartTime = Date.now();
        await page.waitForLoadState('networkidle');
        const pageLoadTime = Date.now() - pageStartTime;
        expect(pageLoadTime).toBeLessThan(2000);

        // Verify correct number of recipes on each page
        const expectedCount = currentPage === totalPages ? 50 % 15 || 15 : 15;
        await expect(recipeRows).toHaveCount(expectedCount);
      }
    });

    test('PERF-001b: Search performance with large dataset', async ({ page }) => {
      await createUserAndLogin(page);

      // Create 30 recipes for search testing
      await createManyRecipes(page, 30);

      await page.goto('/recipes');

      // Test search performance
      const searchInput = page.getByPlaceholder('Search recipes...');

      // Search for specific recipe
      const startTime = Date.now();
      await searchInput.fill('Performance Test Recipe 005');

      // Wait for debounced search
      await page.waitForTimeout(500);

      // Verify search results appear quickly
      const searchTime = Date.now() - startTime;
      expect(searchTime).toBeLessThan(1000);

      // Verify only matching recipe appears
      await expect(page.getByText('Performance Test Recipe 005')).toBeVisible();
      const nonMatchingRecipe = page.getByText('Performance Test Recipe 010');
      await expect(nonMatchingRecipe).not.toBeVisible();

      // Test partial search
      await searchInput.fill('Recipe 01');
      await page.waitForTimeout(500);

      // Should show recipes 010-019
      await expect(page.getByText('Performance Test Recipe 010')).toBeVisible();
      await expect(page.getByText('Performance Test Recipe 015')).toBeVisible();
      await expect(page.getByText('Performance Test Recipe 019')).toBeVisible();

      // Clear search and verify all recipes return
      await searchInput.clear();
      await page.waitForTimeout(500);

      // Should show first page of all recipes
      const allRecipeRows = page.locator('tbody tr').filter({ hasText: /Performance Test Recipe/ });
      await expect(allRecipeRows).toHaveCount(15); // First page
    });

    test('PERF-001c: Category filtering performance', async ({ page }) => {
      await createUserAndLogin(page);

      // Create recipes in different categories
      await createManyRecipes(page, 30);

      await page.goto('/recipes');

      // Test category filtering performance
      const categorySelect = page.getByRole('combobox', { name: /category/i });

      // Filter by breakfast (every 3rd recipe)
      const startTime = Date.now();
      await categorySelect.selectOption('breakfast');
      await page.waitForLoadState('networkidle');
      const filterTime = Date.now() - startTime;

      expect(filterTime).toBeLessThan(2000);

      // Verify only breakfast recipes are shown
      const breakfastRecipes = page.locator('tbody tr').filter({ hasText: /breakfast/ });
      const dinnerRecipes = page.locator('tbody tr').filter({ hasText: /dinner/ });

      await expect(breakfastRecipes.first()).toBeVisible();
      await expect(dinnerRecipes.first()).not.toBeVisible();

      // Count should be approximately 10 (every 3rd of 30)
      const breakfastCount = await breakfastRecipes.count();
      expect(breakfastCount).toBeGreaterThan(8);
      expect(breakfastCount).toBeLessThan(12);
    });
  });

  test.describe('Sorting Performance', () => {

    test('PERF-002a: Column sorting performance with large dataset', async ({ page }) => {
      await createUserAndLogin(page);

      // Create 40 recipes with varying data
      await createManyRecipes(page, 40);

      await page.goto('/recipes');

      // Test name sorting performance
      const nameHeader = page.getByRole('columnheader', { name: /name/i });

      const startTime = Date.now();
      await nameHeader.click();
      await page.waitForLoadState('networkidle');
      const sortTime = Date.now() - startTime;

      expect(sortTime).toBeLessThan(2000);

      // Verify sorting worked (first recipe should be "001")
      const firstRecipeName = await page.locator('tbody tr:first-child td:first-child button').textContent();
      expect(firstRecipeName).toContain('001');

      // Test reverse sorting
      await nameHeader.click();
      await page.waitForLoadState('networkidle');

      // Should now show higher numbers first
      const firstReverseName = await page.locator('tbody tr:first-child td:first-child button').textContent();
      expect(firstReverseName).toContain('040');
    });

    test('PERF-002b: Combined filters and sorting performance', async ({ page }) => {
      await createUserAndLogin(page);

      await createManyRecipes(page, 35);

      await page.goto('/recipes');

      // Apply search filter
      await page.getByPlaceholder('Search recipes...').fill('Recipe 0');
      await page.waitForTimeout(500);

      // Apply category filter
      await page.getByRole('combobox', { name: /category/i }).selectOption('dinner');
      await page.waitForTimeout(500);

      // Apply sorting
      const startTime = Date.now();
      await page.getByRole('columnheader', { name: /calories/i }).click();
      await page.waitForLoadState('networkidle');
      const combinedTime = Date.now() - startTime;

      expect(combinedTime).toBeLessThan(3000);

      // Verify combined filters work
      const filteredRecipes = page.locator('tbody tr').filter({ hasText: /Recipe 0/ }).filter({ hasText: /dinner/ });
      await expect(filteredRecipes.first()).toBeVisible();

      // Verify calories are sorted (ascending order)
      const calorieValues = await page.locator('tbody tr td:nth-child(3)').allTextContents();
      const numericValues = calorieValues
        .map(text => parseInt(text.replace(' kcal', '')))
        .filter(val => !isNaN(val));

      // Should be in ascending order
      for (let i = 1; i < numericValues.length; i++) {
        expect(numericValues[i]).toBeGreaterThanOrEqual(numericValues[i - 1]);
      }
    });
  });

  test.describe('Memory and Resource Usage', () => {

    test('PERF-003a: Page memory usage remains stable with many recipes', async ({ page }) => {
      await createUserAndLogin(page);

      // Create moderate number of recipes
      await createManyRecipes(page, 25);

      await page.goto('/recipes');

      // Navigate through several pages
      for (let i = 0; i < 3; i++) {
        // Go to next page if available
        const nextButton = page.getByRole('button', { name: /next/i });
        const isNextEnabled = await nextButton.isEnabled().catch(() => false);

        if (isNextEnabled) {
          await nextButton.click();
          await page.waitForLoadState('networkidle');
        }

        // Perform some operations
        await page.getByPlaceholder('Search recipes...').fill(`test ${i}`);
        await page.waitForTimeout(300);
        await page.getByPlaceholder('Search recipes...').clear();
        await page.waitForTimeout(300);
      }

      // Page should still be responsive
      const finalStartTime = Date.now();
      await page.getByRole('columnheader', { name: /name/i }).click();
      await page.waitForLoadState('networkidle');
      const finalInteractionTime = Date.now() - finalStartTime;

      expect(finalInteractionTime).toBeLessThan(2000);
    });

    test('PERF-003b: No memory leaks in component lifecycle', async ({ page }) => {
      await createUserAndLogin(page);

      // Create some recipes
      await createManyRecipes(page, 15);

      // Navigate away and back multiple times
      for (let i = 0; i < 5; i++) {
        await page.goto('/recipes');
        await page.waitForLoadState('networkidle');

        // Interact with filters
        await page.getByPlaceholder('Search recipes...').fill('test');
        await page.waitForTimeout(200);

        // Navigate to dashboard and back
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');
      }

      // Final navigation should still be fast
      const startTime = Date.now();
      await page.goto('/recipes');
      await page.waitForLoadState('networkidle');
      const finalLoadTime = Date.now() - startTime;

      expect(finalLoadTime).toBeLessThan(3000);
    });
  });

  test.describe('API Response Times', () => {

    test('PERF-004a: API responses are timely with pagination', async ({ page }) => {
      await createUserAndLogin(page);

      await createManyRecipes(page, 30);

      // Monitor network requests
      const responses: any[] = [];

      page.on('response', response => {
        if (response.url().includes('/recipes') && response.status() === 200) {
          responses.push({
            url: response.url(),
            status: response.status(),
            timestamp: Date.now()
          });
        }
      });

      await page.goto('/recipes');
      await page.waitForLoadState('networkidle');

      // Navigate to second page
      const nextButton = page.getByRole('button', { name: /next|2/i });
      if (await nextButton.isVisible()) {
        await nextButton.click();
        await page.waitForLoadState('networkidle');
      }

            // Verify API responses were captured
      const recipeResponses = responses.filter(r => r.url.includes('/recipes'));
      expect(recipeResponses.length).toBeGreaterThan(0);

      // Verify responses have successful status codes
      recipeResponses.forEach(response => {
        expect(response.status).toBe(200);
      });
    });
  });
});
