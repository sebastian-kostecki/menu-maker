const LOCALE = 'pl-PL'

const defaultNumberFormat = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 })

const dateFormat = new Intl.DateTimeFormat(LOCALE, {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

const pluralRules = new Intl.PluralRules(LOCALE)

const isValidDate = (date) => !Number.isNaN(date.getTime())

// Only the Y-m-d prefix is read, so a full ISO timestamp still parses as a local date.
const parseYmd = (ymd) => {
  const [year, month, day] = String(ymd ?? '').slice(0, 10).split('-').map(Number)

  return new Date(year, month - 1, day)
}

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

  return new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2, ...options }).format(number)
}

/**
 * Formatuje datę (ISO lub Date) po polsku, np. 1 cze 2026.
 */
export function formatDate(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const date = value instanceof Date ? value : new Date(value)

  if (!isValidDate(date)) {
    return ''
  }

  return dateFormat.format(date)
}

// Invalid input renders as '' instead of throwing a RangeError during render.
export const formatDateRange = (startYmd, endYmd) => {
  const start = parseYmd(startYmd)
  const end = parseYmd(endYmd)

  if (!isValidDate(start) || !isValidDate(end)) {
    return ''
  }

  return new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'long', year: 'numeric' }).formatRange(start, end)
}

export const formatDateTime = (iso) => {
  const date = new Date(iso ?? '')

  if (!isValidDate(date)) {
    return ''
  }

  return new Intl.DateTimeFormat(LOCALE, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

/**
 * Wybiera polską formę liczebnika, np. pluralize(3, { one: 'porcja', few: 'porcje', many: 'porcji' }).
 * Ułamki dostają formę `many` (Intl.PluralRules zwraca dla nich „other”).
 */
export const pluralize = (count, { one, few, many }) => {
  const category = pluralRules.select(count)

  if (category === 'one') {
    return one
  }

  if (category === 'few') {
    return few
  }

  return many
}

/**
 * Skrót dnia tygodnia po polsku (np. pon., wt.) dla daty Y-m-d.
 */
export const formatWeekdayShort = (ymd) => {
  const date = parseYmd(ymd)

  if (!isValidDate(date)) {
    return ''
  }

  return new Intl.DateTimeFormat(LOCALE, { weekday: 'short' }).format(date)
}
