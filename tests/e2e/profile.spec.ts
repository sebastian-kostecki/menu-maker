import { test, expect, type Page } from '@playwright/test';
import { generateRandomEmail } from './support/mailpit';

const password = 'Password123!';
const wrongPasswordMessage = 'Podane hasło jest nieprawidłowe.';

async function registerAndOpenProfile(page: Page): Promise<string> {
  const email = generateRandomEmail();

  await page.goto('/register');
  await page.getByLabel('Imię').fill('E2E Profile User');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByLabel('Potwierdź hasło').fill(password);
  await page.getByRole('button', { name: 'Zarejestruj się' }).click();
  await page.waitForURL('**/dashboard');
  await page.goto('/profile');

  return email;
}

test('user can update profile data', async ({ page }) => {
  await registerAndOpenProfile(page);

  const card = page.getByRole('region', { name: 'Dane profilu' });

  await card.getByLabel('Imię').fill('Nowe Imię');
  await card.getByRole('button', { name: 'Zapisz' }).click();
  await expect(card.getByText('Zapisano.')).toBeVisible();

  await page.reload();
  await expect(card.getByLabel('Imię')).toHaveValue('Nowe Imię');
});

test('user can change password', async ({ page }) => {
  await registerAndOpenProfile(page);

  const card = page.getByRole('region', { name: 'Zmiana hasła' });
  const newPassword = 'NewPassword456!';

  await card.getByLabel('Obecne hasło').fill('WrongPassword!');
  await card.getByLabel('Nowe hasło').fill(newPassword);
  await card.getByLabel('Potwierdź hasło').fill(newPassword);
  await card.getByRole('button', { name: 'Zapisz' }).click();
  await expect(card.getByText(wrongPasswordMessage)).toBeVisible();

  await card.getByLabel('Obecne hasło').fill(password);
  await card.getByLabel('Nowe hasło').fill(newPassword);
  await card.getByLabel('Potwierdź hasło').fill(newPassword);
  await card.getByRole('button', { name: 'Zapisz' }).click();
  await expect(card.getByText('Zapisano.')).toBeVisible();
});

test('user can confirm password', async ({ page }) => {
  await registerAndOpenProfile(page);

  await page.goto('/confirm-password');
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Potwierdź' }).click();
  await page.waitForURL('**/dashboard');
  await expect(page).toHaveURL(/dashboard/);
});

test('user can delete the account', async ({ page }) => {
  const email = await registerAndOpenProfile(page);

  await page.getByRole('button', { name: 'Usuń konto' }).click();
  const dialog = page.getByRole('alertdialog');
  await expect(dialog).toBeVisible();

  await dialog.getByLabel('Hasło', { exact: true }).fill('WrongPassword!');
  await dialog.getByRole('button', { name: 'Usuń konto' }).click();
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText(wrongPasswordMessage)).toBeVisible();
  await expect(dialog.getByLabel('Hasło', { exact: true })).toBeFocused();

  await dialog.getByLabel('Hasło', { exact: true }).fill(password);
  await dialog.getByRole('button', { name: 'Usuń konto' }).click();
  await page.waitForURL((url) => url.pathname === '/login');

  await page.goto('/login');
  await page.getByLabel('Adres e-mail').fill(email);
  await page.getByLabel('Hasło').fill(password);
  await page.getByRole('button', { name: 'Zaloguj się' }).click();
  await expect(page).toHaveURL(/login/);
  await expect(page.getByText('Podane dane logowania są nieprawidłowe.')).toBeVisible();
});
