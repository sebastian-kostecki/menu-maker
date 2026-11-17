import { test, expect, type Page } from '@playwright/test';
import { expectAppFontLoaded } from './support/fonts';

test.skip(
  !process.env.PLAYWRIGHT_VISUAL_DOCKER,
  'Run via npm run test:visual (Docker-pinned renderer)'
);

const sections = [
  { name: 'Kolory', slug: 'kolory' },
  { name: 'Typografia', slug: 'typografia' },
  { name: 'Kształt i cienie', slug: 'ksztalt-i-cienie' },
  { name: 'Przyciski', slug: 'przyciski' },
  { name: 'Odznaki', slug: 'odznaki' },
  { name: 'Komunikaty', slug: 'komunikaty' },
  { name: 'Formularze', slug: 'formularze' },
  { name: 'Karta i tabela', slug: 'karta-i-tabela' },
  { name: 'Ładowanie i rozwijanie', slug: 'ladowanie-i-rozwijanie' },
  { name: 'Paginacja', slug: 'paginacja' },
  { name: 'Nakładki', slug: 'nakladki' },
  { name: 'Powłoka', slug: 'powloka' },
  { name: 'Komponenty aplikacji', slug: 'komponenty-aplikacji' },
  { name: 'Jadłospis', slug: 'jadlospis' }
];

async function openKitchenSink(page: Page) {
  await page.goto('/dev/design-system');
  await expect(page.getByRole('heading', { level: 1, name: 'Design system' })).toBeVisible();
  await expectAppFontLoaded(page);
}

function section(page: Page, name: string) {
  return page.getByRole('region', { name, exact: true });
}

test.describe('sections', () => {
  for (const { name, slug } of sections) {
    test(`section ${name}`, async ({ page }) => {
      await openKitchenSink(page);
      await expect(section(page, name)).toHaveScreenshot(`section-${slug}.png`);
    });
  }
});

test('focus-visible on the outline button', async ({ page }) => {
  await openKitchenSink(page);
  const buttons = section(page, 'Przyciski').getByRole('button');
  const outline = buttons.getByText('outline', { exact: true }).first();
  await buttons.getByText('destructive', { exact: true }).first().focus();
  // Keyboard navigation (not .focus()) is what triggers :focus-visible.
  await page.keyboard.press('Tab');
  // The outline button shows the ring against the page background.
  await expect(outline).toBeFocused();
  await expect(section(page, 'Przyciski')).toHaveScreenshot('focus-visible.png');
});

test('button hover on the outline button', async ({ page }) => {
  await openKitchenSink(page);
  const outline = section(page, 'Przyciski').getByRole('button', { name: 'outline', exact: true }).first();
  await outline.hover();
  await expect(outline.locator('..')).toHaveScreenshot('button-hover.png');
});

test('button pressed on the outline button', async ({ page }) => {
  await openKitchenSink(page);
  const outline = section(page, 'Przyciski').getByRole('button', { name: 'outline', exact: true }).first();
  await outline.hover();
  await page.mouse.down();
  try {
    await expect(outline.locator('..')).toHaveScreenshot('button-pressed.png');
  } finally {
    await page.mouse.up();
  }
});

test('input focus-visible', async ({ page }) => {
  await openKitchenSink(page);
  const input = page.locator('#ds-input-default');
  // Start the Tab sequence from the section heading, the input is the first control after it.
  await page.locator('#ds-formularze').evaluate((heading) => {
    heading.setAttribute('tabindex', '-1');
    heading.focus();
  });
  await page.keyboard.press('Tab');
  await expect(input).toBeFocused();
  await expect(input).toHaveScreenshot('input-focus-visible.png');
});

test('chip focus-visible', async ({ page }) => {
  await openKitchenSink(page);
  const przyciski = section(page, 'Przyciski');
  // The last enabled button before the chips; Tab skips the disabled ones.
  await przyciski.getByRole('button', { name: 'Dodaj przepis' }).focus();
  await page.keyboard.press('Tab');
  const chip = przyciski.getByRole('button', { name: /^Wszystkie/ }).first();
  await expect(chip).toBeFocused();
  await expect(chip.locator('..')).toHaveScreenshot('chip-focus-visible.png');
});

test('shell nav hover', async ({ page }) => {
  await openKitchenSink(page);
  const box = page.getByTestId('shell-sidebar-expanded');
  await section(page, 'Powłoka')
    .getByRole('navigation')
    .first()
    .getByRole('link', { name: 'Jadłospisy', exact: true })
    .hover();
  await expect(box).toHaveScreenshot('shell-nav-hover.png');
});

test('shell nav focus-visible', async ({ page }) => {
  await openKitchenSink(page);
  const box = page.getByTestId('shell-sidebar-expanded');
  await box.getByRole('button', { name: 'Zwiń panel boczny' }).focus();
  // Keyboard navigation (not .focus()) is what triggers :focus-visible.
  await page.keyboard.press('Tab');
  await expect(box.getByRole('link', { name: 'Pulpit', exact: true })).toBeFocused();
  await expect(box).toHaveScreenshot('shell-nav-focus-visible.png');
});

test.describe('overlays', () => {
  test('dialog', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Otwórz dialog' }).click();
    const overlay = page.getByRole('dialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-dialog.png');
  });

  test('alert dialog', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Otwórz potwierdzenie' }).click();
    const overlay = page.getByRole('alertdialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-alert-dialog.png');
  });

  test('confirm dialog', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Otwórz ConfirmDialog', exact: true }).click();
    const overlay = page.getByRole('alertdialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-confirm-dialog.png');
  });

  test('confirm dialog processing', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Pokaż dialog w toku', exact: true }).click();
    const overlay = page.getByRole('alertdialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-confirm-dialog-processing.png');
  });

  test('confirm dialog with field', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Potwierdź z polem', exact: true }).click();
    const overlay = page.getByRole('alertdialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-confirm-dialog-field.png');
  });

  test('sheet', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Otwórz panel' }).click();
    const overlay = page.getByRole('dialog');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-sheet.png');
  });

  test('dropdown menu', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Otwórz menu' }).click();
    const overlay = page.getByRole('menu');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-dropdown-menu.png');
  });

  test('pdf download menu', async ({ page }) => {
    await openKitchenSink(page);
    await section(page, 'Jadłospis').getByRole('button', { name: 'Pobierz PDF' }).click();
    const overlay = page.getByRole('menu');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-pdf-download-menu.png');
  });

  test('user menu', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Menu użytkownika' }).click();
    const overlay = page.getByRole('menu');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-user-menu.png');
  });

  test('select', async ({ page }) => {
    await openKitchenSink(page);
    // The Select trigger and the Combobox input share the name "Kategoria";
    // the Select comes first in the DOM.
    await section(page, 'Formularze').getByRole('combobox', { name: 'Kategoria' }).first().click();
    const overlay = page.getByRole('listbox');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-select.png');
  });

  test('combobox', async ({ page }) => {
    await openKitchenSink(page);
    const input = section(page, 'Formularze').getByRole('combobox', { name: 'Kategoria' }).last();
    await input.focus();
    await page.keyboard.press('ArrowDown');
    const overlay = page.getByRole('listbox');
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-combobox.png');
  });

  test('toast', async ({ page }) => {
    await openKitchenSink(page);
    await page.getByRole('button', { name: 'Pokaż powiadomienie' }).click();
    const overlay = page.locator('[data-sonner-toast]').filter({ hasText: 'Zapisano przepis' });
    await expect(overlay).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(overlay).toHaveScreenshot('overlay-toast.png');
  });
});
