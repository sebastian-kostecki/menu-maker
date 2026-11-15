const LOCALE = 'pl-PL'

const isValidDate = (date) => !Number.isNaN(date.getTime())

// Only the Y-m-d prefix is read, so a full ISO timestamp still parses as a local date.
const parseYmd = (ymd) => {
  const [year, month, day] = String(ymd ?? '').slice(0, 10).split('-').map(Number)

  return new Date(year, month - 1, day)
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

export const pluralize = (count, { one, few, many }) => {
  const category = new Intl.PluralRules(LOCALE).select(count)

  if (category === 'one') {
    return one
  }

  if (category === 'few') {
    return few
  }

  return many
}
