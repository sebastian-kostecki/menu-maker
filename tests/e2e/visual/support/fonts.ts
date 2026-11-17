import { expect, type Page } from '@playwright/test';

// Latin plus Polish letters, so both the latin and latin-ext subsets are requested.
const SAMPLE_TEXT = 'Aąęłśżź';
const LATIN_EXT_CODE_POINT = 0x142; // ł

type LoadedFace = { family: string; status: string; weight: string; unicodeRange: string };

function coversCodePoint(unicodeRange: string, codePoint: number): boolean {
  return unicodeRange.split(',').some((part) => {
    const range = part.trim().replace(/^U\+/i, '');
    if (range.includes('?')) {
      return codePoint >= parseInt(range.replace(/\?/g, '0'), 16) && codePoint <= parseInt(range.replace(/\?/g, 'F'), 16);
    }
    const [start, end = start] = range.split('-');
    return codePoint >= parseInt(start, 16) && codePoint <= parseInt(end, 16);
  });
}

// document.fonts.check() is true when no face matches, so load each weight with a
// Polish sample and assert that the exact weight came back loaded, latin-ext included.
export async function expectAppFontLoaded(page: Page) {
  const results = await page.evaluate(async (text) => {
    await document.fonts.ready;
    const out: { weight: number; faces: LoadedFace[] }[] = [];
    for (const weight of [400, 500, 600, 700, 800]) {
      const faces = await document.fonts.load(`${weight} 16px "Bricolage Grotesque"`, text);
      out.push({
        weight,
        faces: faces.map((face) => ({
          family: face.family.replace(/["']/g, ''),
          status: face.status,
          weight: face.weight,
          unicodeRange: face.unicodeRange
        }))
      });
    }
    return out;
  }, SAMPLE_TEXT);

  for (const { weight, faces } of results) {
    const hint = `(fonts.bunny.net / cdn.jsdelivr.net unreachable?) - weight ${weight}`;
    const bricolage = faces.filter((face) => face.family === 'Bricolage Grotesque' && face.weight === String(weight));
    expect(bricolage.length, `Bricolage Grotesque has no face at this exact weight ${hint}`).toBeGreaterThan(0);
    expect(
      bricolage.every((face) => face.status === 'loaded'),
      `Bricolage Grotesque face did not load ${hint}`
    ).toBe(true);
    expect(
      bricolage.some((face) => coversCodePoint(face.unicodeRange, LATIN_EXT_CODE_POINT)),
      `Bricolage Grotesque latin-ext (Polish) subset did not load ${hint}`
    ).toBe(true);
  }
}
