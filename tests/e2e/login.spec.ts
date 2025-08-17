import { test, expect } from '@playwright/test';

const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-${random}@example.com`;
};

test('user can register and see dashboard', async ({ page }) => {
  const email = generateRandomEmail();
  const password = 'Password123!';

  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E User');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');
  await expect(page).toHaveURL(/dashboard/);
});

test('user can log in with valid credentials', async ({ page, browser }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL ?? 'http://localhost';
  const email = generateRandomEmail();
  const password = 'Password123!';

  // Create a user via the UI (registration)
  await page.goto('/register');
  await page.getByLabel('Name').fill('E2E Login User');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await page.waitForURL('**/dashboard');

  // Start a fresh context to simulate a logged-out user
  const context = await browser.newContext();
  const page2 = await context.newPage();
  await page2.goto(`${baseURL}/login`);
  await page2.getByLabel('Email').fill(email);
  await page2.getByLabel('Password').fill(password);
  await page2.getByRole('button', { name: 'Log in' }).click();
  await page2.waitForURL('**/dashboard');
  await expect(page2).toHaveURL(/dashboard/);
  await context.close();
});

test('shows error on invalid login credentials', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(generateRandomEmail());
  await page.getByLabel('Password').fill('WrongPassword!');
  await page.getByRole('button', { name: 'Log in' }).click();

  // Stays on login and shows the default Laravel error message
  await expect(page).toHaveURL(/login/);
  await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
});


