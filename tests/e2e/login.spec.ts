import { test, expect } from '@playwright/test';

test('user can register and see dashboard', async ({ page }) => {
  const random = Math.random().toString(36).slice(2, 8);
  const email = `e2e-${random}@example.com`;
  const password = 'Password123!';

  await page.goto('/register');
  await page.fill('input[name="name"]', 'E2E User');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.fill('input[name="password_confirmation"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  await expect(page).toHaveURL(/dashboard/);
});


