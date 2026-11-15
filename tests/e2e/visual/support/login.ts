import { type Page } from '@playwright/test';
import { expectFigtreeLoaded } from './fonts';

// Visual user seeded by E2eMealPlanSeeder (deterministic recipes for the screenshots).
export async function logInAsVisualUser(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill('e2e-planner-visual@example.com');
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
  await expectFigtreeLoaded(page);
}
