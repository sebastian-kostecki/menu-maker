/**
 * Seven Y-m-d strings starting at `startYmd`, computed in UTC so a DST change never skips or repeats a day.
 * Invalid input yields [] instead of throwing a RangeError during render.
 */
export function weekDays(startYmd) {
  const [year, month, day] = String(startYmd ?? '').slice(0, 10).split('-').map(Number)

  if ([year, month, day].some(Number.isNaN)) {
    return []
  }

  return Array.from({ length: 7 }, (_, offset) => new Date(Date.UTC(year, month - 1, day + offset)).toISOString().slice(0, 10))
}

/**
 * The browser's local date as Y-m-d (not toISOString(), which is UTC).
 */
export function todayYmd() {
  const now = new Date()
  const pad = (value) => String(value).padStart(2, '0')

  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}
