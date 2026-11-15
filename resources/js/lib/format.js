const defaultNumberFormat = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2 })

const dateFormat = new Intl.DateTimeFormat('pl-PL', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

/**
 * Formatuje liczbę (lub liczbę zapisaną jako tekst) po polsku, np. 1,5.
 * Zwraca pusty tekst dla null, pustego (lub samych spacji) tekstu i wartości nienumerycznych.
 */
export function formatNumber(value, options) {
  if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) {
    return ''
  }

  const number = typeof value === 'number' ? value : Number(value)

  if (Number.isNaN(number)) {
    return ''
  }

  if (!options) {
    return defaultNumberFormat.format(number)
  }

  return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2, ...options }).format(number)
}

/**
 * Formatuje datę (ISO lub Date) po polsku, np. 1 cze 2026.
 */
export function formatDate(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const date = value instanceof Date ? value : new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return dateFormat.format(date)
}

/**
 * Wybiera polską formę liczebnika: [jeden, kilka, wiele]. Ułamki (np. 2,5 porcji) dostają formę „wiele”.
 */
export function pluralize(count, [one, few, many]) {
  const number = Number(count)

  if (!Number.isInteger(number)) {
    return many
  }

  const abs = Math.abs(number)

  if (abs === 1) {
    return one
  }

  const lastDigit = abs % 10
  const lastTwoDigits = abs % 100

  if (lastDigit >= 2 && lastDigit <= 4 && !(lastTwoDigits >= 12 && lastTwoDigits <= 14)) {
    return few
  }

  return many
}
