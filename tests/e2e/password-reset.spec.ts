import { test, expect } from '@playwright/test';
import { findResetMessage, findResetPath } from './support/mailpit';

const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-${random}@example.com`;
};

test('user can reset the password through the e-mailed link', async ({ page, browser }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL ?? 'http://localhost';
  const email = generateRandomEmail();
  const password = 'Password123!';
  const newPassword = 'NewPassword456!';

  await page.goto('/register');
  await page.getByLabel('Imię').fill('E2E Reset User');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByLabel('Potwierdź hasło').fill(password);
  await page.getByRole('button', { name: 'Zarejestruj się' }).click();
  await page.waitForURL('**/dashboard');

  // Fresh context simulates a logged-out user
  const context = await browser.newContext({ baseURL });
  const page2 = await context.newPage();
  await page2.goto('/forgot-password');
  await page2.getByLabel('Adres e-mail').fill(email);
  await page2.getByRole('button', { name: 'Wyślij link do resetu hasła' }).click();
  await expect(page2.getByText('Wysłaliśmy e-mailem link do resetu hasła.')).toBeVisible();

  const message = await findResetMessage(email);
  expect(message.Subject).toBe('Zresetuj hasło');

  const resetPath = await findResetPath(email);
  await page2.goto(resetPath);
  await page2.getByLabel('Nowe hasło').fill(newPassword);
  await page2.getByLabel('Potwierdź hasło').fill(newPassword);
  await page2.getByRole('button', { name: 'Zresetuj hasło' }).click();

  await page2.waitForURL('**/login');
  await expect(page2.getByText('Twoje hasło zostało zresetowane.')).toBeVisible();

  await page2.getByLabel('Adres e-mail').fill(email);
  await page2.getByLabel('Hasło').fill(newPassword);
  await page2.getByRole('button', { name: 'Zaloguj się' }).click();
  await page2.waitForURL('**/dashboard');
  await expect(page2).toHaveURL(/dashboard/);
  await context.close();
});
