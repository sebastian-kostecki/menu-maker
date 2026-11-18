const LOCALE = 'pl-PL'

const defaultNumberFormat = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 })

const dateFormat = new Intl.DateTimeFormat(LOCALE, {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

const pluralRules = new Intl.PluralRules(LOCALE)

const isValidDate = (date) => !Number.isNaN(date.getTime())

const BARE_YMD = /^\d{4}-\d{2}-\d{2}$/

// Only the Y-m-d prefix is read, so a full ISO timestamp still parses as a local date.
// An impossible date (2023-02-31) is an Invalid Date instead of rolling over into the next month.
const parseYmd = (ymd) => {
  const [year, month, day] = String(ymd ?? '').slice(0, 10).split('-').map(Number)
  const date = new Date(year, month - 1, day)

  return date.getMonth() === month - 1 && date.getDate() === day ? date : new Date(Number.NaN)
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

  // A bare Y-m-d is a calendar date: new Date() would read it as UTC midnight and show a day early west of UTC.
  const date = value instanceof Date
    ? value
    : (typeof value === 'string' && BARE_YMD.test(value) ? parseYmd(value) : new Date(value))

  if (!isValidDate(date)) {
    return ''
  }

  return dateFormat.format(date)
}

/**
 * Pełne ukończone lata od daty urodzenia Y-m-d (0 poniżej roku); null dla błędnej daty.
 */
export const ageInYears = (birthYmd, today = new Date()) => {
  if (typeof birthYmd !== 'string' || !BARE_YMD.test(birthYmd.slice(0, 10))) {
    return null
  }

  const birth = parseYmd(birthYmd)

  if (!isValidDate(birth) || !isValidDate(today)) {
    return null
  }

  let age = today.getFullYear() - birth.getFullYear()

  if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) {
    age -= 1
  }

  return age < 0 ? null : age
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
 * Dzień i miesiąc po polsku (np. 29.06) dla daty Y-m-d.
 */
export const formatDayMonth = (ymd) => {
  const date = parseYmd(ymd)

  if (!isValidDate(date)) {
    return ''
  }

  return new Intl.DateTimeFormat(LOCALE, { day: '2-digit', month: '2-digit' }).format(date)
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
