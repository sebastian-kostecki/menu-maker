import { test, expect, type Page } from '@playwright/test';

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
  { name: 'Nakładki', slug: 'nakladki' }
];

async function openKitchenSink(page: Page) {
  await page.goto('/dev/design-system');
  await expect(page.getByRole('heading', { level: 1, name: 'Design system' })).toBeVisible();

  // document.fonts.check() is true when no face matches, so load each weight and
  // assert a loaded Figtree face came back.
  const results = await page.evaluate(async () => {
    await document.fonts.ready;
    const out: { weight: number; faces: { family: string; status: string }[] }[] = [];
    for (const weight of [400, 500, 600]) {
      const faces = await document.fonts.load(`${weight} 16px Figtree`);
      out.push({
        weight,
        faces: faces.map((face) => ({ family: face.family, status: face.status }))
      });
    }
    return out;
  });

  for (const { weight, faces } of results) {
    const loaded = faces.some(
      (face) => face.family.replace(/["']/g, '') === 'Figtree' && face.status === 'loaded'
    );
    expect(loaded, `Figtree did not load (fonts.bunny.net unreachable?) - weight ${weight}`).toBe(true);
  }
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
  // The default button's ring is the same green as its fill, so the outline button shows the ring.
  await expect(outline).toBeFocused();
  await expect(section(page, 'Przyciski')).toHaveScreenshot('focus-visible.png');
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
