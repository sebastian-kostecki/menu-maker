// Helper functions for Recipe E2E tests
// Provides reusable functionality for user creation, recipe manipulation, and common assertions

import { Page, expect } from '@playwright/test';

export interface RecipeData {
  name: string;
  category: 'breakfast' | 'dinner' | 'supper';
  calories: number;
  servings: number;
  instructions: string;
  ingredients: Array<{
    name: string;
    quantity: number;
    unit: string;
  }>;
}

export interface UserCredentials {
  email: string;
  password: string;
}

/**
 * Generates a unique email address for testing
 */
export const generateRandomEmail = (prefix: string = 'e2e-test'): string => {
  const random = Math.random().toString(36).slice(2, 8);
  const timestamp = Date.now().toString(36);
  return `${prefix}-${random}-${timestamp}@example.com`;
};

/**
 * Creates a new user account and logs in
 */
export const createUserAndLogin = async (page: Page, name: string = 'E2E Test User'): Promise<UserCredentials> => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  await page.goto('/register');
  await page.getByLabel('Name').fill(name);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');

  return { email, password };
};

/**
 * Logs in with existing user credentials
 */
export const loginUser = async (page: Page, credentials: UserCredentials): Promise<void> => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(credentials.email);
  await page.getByLabel('Password').fill(credentials.password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL('**/dashboard');
};

/**
 * Navigates to recipes page and verifies successful navigation
 */
export const navigateToRecipes = async (page: Page): Promise<void> => {
  await page.goto('/recipes');
  await expect(page).toHaveURL(/recipes$/);
  await expect(page.getByRole('heading', { name: 'Przepisy' })).toBeVisible();
};

/**
 * Fills the recipe creation/edit form with provided data
 */
export const fillRecipeForm = async (page: Page, recipeData: RecipeData): Promise<void> => {
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

/**
 * Creates a complete recipe from recipes index page
 */
export const createRecipe = async (page: Page, recipeData: RecipeData): Promise<void> => {
  await navigateToRecipes(page);
  await page.getByRole('button', { name: /create|add|new/i }).click();
  await expect(page).toHaveURL(/recipes\/create$/);

  await fillRecipeForm(page, recipeData);
  await page.getByRole('button', { name: /save|create/i }).click();

  await expect(page).toHaveURL(/recipes$/);
  await expect(page.getByText(recipeData.name)).toBeVisible();
};

/**
 * Creates multiple recipes for testing pagination, filtering, etc.
 */
export const createMultipleRecipes = async (page: Page, count: number, baseData?: Partial<RecipeData>): Promise<RecipeData[]> => {
  const categories: Array<'breakfast' | 'dinner' | 'supper'> = ['breakfast', 'dinner', 'supper'];
  const createdRecipes: RecipeData[] = [];

  for (let i = 1; i <= count; i++) {
    const recipe: RecipeData = {
      name: `Test Recipe ${i.toString().padStart(3, '0')}`,
      category: categories[i % categories.length],
      calories: 200 + (i * 10),
      servings: 2 + (i % 4),
      instructions: `Instructions for test recipe ${i}. Mix ingredients and cook properly.`,
      ingredients: [
        { name: `Ingredient A${i}`, quantity: i, unit: 'g' },
        { name: `Ingredient B${i}`, quantity: i * 0.5, unit: 'ml' }
      ],
      ...baseData
    };

    await createRecipe(page, recipe);
    createdRecipes.push(recipe);
  }

  return createdRecipes;
};

/**
 * Searches for recipes using the search input
 */
export const searchRecipes = async (page: Page, searchTerm: string): Promise<void> => {
  const searchInput = page.getByPlaceholder('Search recipes...');
  await searchInput.fill(searchTerm);
  await page.waitForTimeout(500); // Wait for debounce
};

/**
 * Filters recipes by category
 */
export const filterByCategory = async (page: Page, category: string): Promise<void> => {
  const categorySelect = page.getByRole('combobox', { name: /category/i }).or(page.locator('select'));
  await categorySelect.selectOption(category);
  await page.waitForTimeout(300);
};

/**
 * Resets all filters on the recipes page
 */
export const resetFilters = async (page: Page): Promise<void> => {
  const resetButton = page.getByRole('button', { name: /reset|clear/i });
  await resetButton.click();
};

/**
 * Sorts recipes by clicking on a column header
 */
export const sortByColumn = async (page: Page, columnName: string): Promise<void> => {
  const header = page.getByRole('columnheader', { name: new RegExp(columnName, 'i') });
  await header.click();
  await page.waitForTimeout(500);
};

/**
 * Gets a recipe row by recipe name
 */
export const getRecipeRow = (page: Page, recipeName: string) => {
  return page.locator('tr').filter({ hasText: recipeName });
};

/**
 * Verifies that a recipe appears in the table with correct data
 */
export const verifyRecipeInTable = async (page: Page, recipeData: RecipeData): Promise<void> => {
  const row = getRecipeRow(page, recipeData.name);
  await expect(row).toBeVisible();

  // Verify recipe data in table
  await expect(row.getByText(recipeData.name)).toBeVisible();
  await expect(row.getByText(recipeData.category)).toBeVisible();
  await expect(row.getByText(`${recipeData.calories} kcal`)).toBeVisible();
  await expect(row.getByText(recipeData.servings.toString())).toBeVisible();

  // Verify action buttons are present
  await expect(row.getByRole('button', { name: `View ${recipeData.name}` })).toBeVisible();
  await expect(row.getByRole('button', { name: `Edit ${recipeData.name}` })).toBeVisible();
  await expect(row.getByRole('button', { name: `Delete ${recipeData.name}` })).toBeVisible();
};

/**
 * Views a recipe by clicking on its name
 */
export const viewRecipe = async (page: Page, recipeName: string): Promise<void> => {
  await page.getByText(recipeName).click();
  await expect(page).toHaveURL(/recipes\/\d+$/);
  await expect(page.getByRole('heading', { name: recipeName })).toBeVisible();
};

/**
 * Edits a recipe by clicking the edit button
 */
export const editRecipe = async (page: Page, recipeName: string): Promise<void> => {
  const row = getRecipeRow(page, recipeName);
  await row.getByRole('button', { name: `Edit ${recipeName}` }).click();
  await expect(page).toHaveURL(/recipes\/\d+\/edit$/);
  await expect(page.getByLabel('Recipe Name')).toHaveValue(recipeName);
};

/**
 * Deletes a recipe using the delete confirmation modal
 */
export const deleteRecipe = async (page: Page, recipeName: string, confirm: boolean = true): Promise<void> => {
  const row = getRecipeRow(page, recipeName);
  await row.getByRole('button', { name: `Delete ${recipeName}` }).click();

  // Verify modal appears
  await expect(page.getByText('Delete Recipe')).toBeVisible();
  await expect(page.getByText(`Are you sure you want to delete "${recipeName}"`)).toBeVisible();

  if (confirm) {
    await page.getByRole('button', { name: 'Delete Recipe' }).click();
    await expect(page.getByText('Delete Recipe')).not.toBeVisible();
    await expect(page.getByText(recipeName)).not.toBeVisible();
  } else {
    await page.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.getByText('Delete Recipe')).not.toBeVisible();
    await expect(page.getByText(recipeName)).toBeVisible();
  }
};

/**
 * Verifies empty state is displayed when no recipes exist
 */
export const verifyEmptyState = async (page: Page): Promise<void> => {
  await expect(page.getByText('Brak przepisów')).toBeVisible();
  await expect(page.getByText('Rozpocznij od dodania pierwszego przepisu')).toBeVisible();
  await expect(page.getByText('(0 przepisów)')).toBeVisible();
};

/**
 * Verifies no results state when search/filter yields no matches
 */
export const verifyNoResultsState = async (page: Page): Promise<void> => {
  await expect(page.getByText('No recipes found')).toBeVisible();
};

/**
 * Verifies pagination is present and functional
 */
export const verifyPagination = async (page: Page, expectedPages: number): Promise<void> => {
  const pagination = page.locator('[data-testid="pagination"]').or(page.getByRole('navigation', { name: /pagination/i }));
  await expect(pagination).toBeVisible();

  if (expectedPages > 1) {
    await expect(page.getByRole('button', { name: '1' })).toBeVisible();
    await expect(page.getByRole('button', { name: expectedPages.toString() })).toBeVisible();

    const nextButton = page.getByRole('button', { name: /next/i });
    const prevButton = page.getByRole('button', { name: /previous|prev/i });

    await expect(nextButton).toBeVisible();
    await expect(prevButton).toBeVisible();
  }
};

/**
 * Navigates to a specific page using pagination
 */
export const navigateToPage = async (page: Page, pageNumber: number): Promise<void> => {
  if (pageNumber === 1) {
    // Use previous button or page 1 button
    const prevButton = page.getByRole('button', { name: /previous|prev/i });
    const page1Button = page.getByRole('button', { name: '1' });

    if (await prevButton.isVisible() && await prevButton.isEnabled()) {
      await prevButton.click();
    } else if (await page1Button.isVisible()) {
      await page1Button.click();
    }
  } else {
    // Use next button or specific page button
    const pageButton = page.getByRole('button', { name: pageNumber.toString() });
    const nextButton = page.getByRole('button', { name: /next/i });

    if (await pageButton.isVisible()) {
      await pageButton.click();
    } else if (await nextButton.isVisible() && await nextButton.isEnabled()) {
      await nextButton.click();
    }
  }

  await page.waitForTimeout(500);
  await expect(page).toHaveURL(new RegExp(`[?&]page=${pageNumber}`));
};

/**
 * Waits for the page to finish loading
 */
export const waitForPageLoad = async (page: Page): Promise<void> => {
  await page.waitForLoadState('networkidle');
};

/**
 * Verifies recipe count in the page header
 */
export const verifyRecipeCount = async (page: Page, expectedCount: number): Promise<void> => {
  if (expectedCount === 0) {
    await expect(page.getByText('(0 przepisów)')).toBeVisible();
  } else if (expectedCount === 1) {
    await expect(page.getByText('(1 przepis)')).toBeVisible();
  } else if (expectedCount < 5) {
    await expect(page.getByText(`(${expectedCount} przepisy)`)).toBeVisible();
  } else {
    await expect(page.getByText(`(${expectedCount} przepisów)`)).toBeVisible();
  }
};

/**
 * Verifies that only specified recipes are visible in the table
 */
export const verifyVisibleRecipes = async (page: Page, recipeNames: string[]): Promise<void> => {
  for (const name of recipeNames) {
    await expect(page.getByText(name)).toBeVisible();
  }

  // Count total visible recipe rows
  const recipeRows = page.locator('tbody tr').filter({ hasText: /Test Recipe|Component Test|Performance Test|A11Y Test/ });
  await expect(recipeRows).toHaveCount(recipeNames.length);
};

/**
 * Sample recipe data for testing
 */
export const sampleRecipes: RecipeData[] = [
  {
    name: 'Sample Breakfast Recipe',
    category: 'breakfast',
    calories: 350,
    servings: 2,
    instructions: 'Mix ingredients and cook on medium heat until golden brown.',
    ingredients: [
      { name: 'Eggs', quantity: 2, unit: 'pieces' },
      { name: 'Flour', quantity: 100, unit: 'g' },
      { name: 'Milk', quantity: 200, unit: 'ml' }
    ]
  },
  {
    name: 'Sample Dinner Recipe',
    category: 'dinner',
    calories: 550,
    servings: 4,
    instructions: 'Prepare all ingredients, cook pasta according to package instructions, combine with sauce.',
    ingredients: [
      { name: 'Pasta', quantity: 400, unit: 'g' },
      { name: 'Tomato Sauce', quantity: 500, unit: 'ml' },
      { name: 'Cheese', quantity: 150, unit: 'g' },
      { name: 'Herbs', quantity: 1, unit: 'tablespoon' }
    ]
  },
  {
    name: 'Sample Supper Recipe',
    category: 'supper',
    calories: 280,
    servings: 2,
    instructions: 'Wash vegetables, chop into bite-sized pieces, mix with dressing and serve.',
    ingredients: [
      { name: 'Lettuce', quantity: 200, unit: 'g' },
      { name: 'Tomatoes', quantity: 150, unit: 'g' },
      { name: 'Cucumber', quantity: 100, unit: 'g' },
      { name: 'Olive Oil', quantity: 30, unit: 'ml' }
    ]
  }
];

/**
 * Performance measurement helpers
 */
export const measurePageLoadTime = async (page: Page, url: string): Promise<number> => {
  const startTime = Date.now();
  await page.goto(url);
  await page.waitForLoadState('networkidle');
  return Date.now() - startTime;
};

export const measureInteractionTime = async (page: Page, action: () => Promise<void>): Promise<number> => {
  const startTime = Date.now();
  await action();
  await page.waitForLoadState('networkidle');
  return Date.now() - startTime;
};

/**
 * Accessibility testing helpers
 */
export const verifyFocusIndicator = async (page: Page, element: any): Promise<boolean> => {
  await element.focus();

  const focusStyle = await element.evaluate((el: Element) => {
    const styles = window.getComputedStyle(el);
    return {
      outline: styles.outline,
      outlineColor: styles.outlineColor,
      boxShadow: styles.boxShadow,
      borderColor: styles.borderColor
    };
  });

  return (
    focusStyle.outline !== 'none' ||
    focusStyle.boxShadow !== 'none' ||
    focusStyle.borderColor !== 'initial'
  );
};

export const verifyAriaAttributes = async (page: Page, selector: string, expectedAttributes: Record<string, string>): Promise<void> => {
  const element = page.locator(selector);

  for (const [attr, expectedValue] of Object.entries(expectedAttributes)) {
    const actualValue = await element.getAttribute(attr);
    expect(actualValue).toBe(expectedValue);
  }
};

export const checkColorContrast = async (page: Page, element: any): Promise<{ color: string; backgroundColor: string }> => {
  return await element.evaluate((el: Element) => {
    const styles = window.getComputedStyle(el);
    return {
      color: styles.color,
      backgroundColor: styles.backgroundColor
    };
  });
};
