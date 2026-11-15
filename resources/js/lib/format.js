const LOCALE = 'pl-PL'

const parseYmd = (ymd) => {
  const [year, month, day] = String(ymd).split('-').map(Number)

  return new Date(year, month - 1, day)
}

export const formatDateRange = (startYmd, endYmd) =>
  new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'long', year: 'numeric' }).formatRange(
    parseYmd(startYmd),
    parseYmd(endYmd),
  )

export const formatDateTime = (iso) =>
  new Intl.DateTimeFormat(LOCALE, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(iso))

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
