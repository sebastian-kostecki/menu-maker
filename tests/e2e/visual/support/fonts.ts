import { expect, type Page } from '@playwright/test';

// document.fonts.check() is true when no face matches, so load each weight and
// assert a loaded Bricolage Grotesque face came back.
export async function expectAppFontLoaded(page: Page) {
  const results = await page.evaluate(async () => {
    await document.fonts.ready;
    const out: { weight: number; faces: { family: string; status: string }[] }[] = [];
    for (const weight of [400, 500, 600, 700, 800]) {
      const faces = await document.fonts.load(`${weight} 16px "Bricolage Grotesque"`);
      out.push({
        weight,
        faces: faces.map((face) => ({ family: face.family, status: face.status }))
      });
    }
    return out;
  });

  for (const { weight, faces } of results) {
    const loaded = faces.some(
      (face) => face.family.replace(/["']/g, '') === 'Bricolage Grotesque' && face.status === 'loaded'
    );
    expect(loaded, `Bricolage Grotesque did not load (fonts.bunny.net / cdn.jsdelivr.net unreachable?) - weight ${weight}`).toBe(true);
  }
}
