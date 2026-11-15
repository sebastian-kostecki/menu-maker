/**
 * Returns the Polish plural form for a count (the caller formats the number itself).
 *
 * @param {number} count
 * @param {string} one  Form for 1 (e.g. "rok")
 * @param {string} few  Form for 2-4 except 12-14 (e.g. "lata")
 * @param {string} many Form for everything else (e.g. "lat")
 * @returns {string}
 */
export function pluralizePl(count, one, few, many) {
  if (count === 1) {
    return one
  }

  const lastDigit = count % 10
  const lastTwoDigits = count % 100

  if (lastDigit >= 2 && lastDigit <= 4 && !(lastTwoDigits >= 12 && lastTwoDigits <= 14)) {
    return few
  }

  return many
}
