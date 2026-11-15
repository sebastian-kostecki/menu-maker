import { expect, type Page } from '@playwright/test';

// document.fonts.check() is true when no face matches, so load each weight and
// assert a loaded Figtree face came back.
export async function expectFigtreeLoaded(page: Page) {
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
