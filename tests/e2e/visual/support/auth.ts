import type { Page } from '@playwright/test';
import { expectFigtreeLoaded } from './fonts';

export async function logIn(page: Page, email = 'e2e-planner-visual@example.com') {
  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill('Password123!');
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await page.waitForURL('**/dashboard');
  await expectFigtreeLoaded(page);
}
