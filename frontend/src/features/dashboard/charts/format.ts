// `daily` dates are calendar days in the business timezone, so they are formatted
// as plain dates (read as UTC midnight) and never shifted into the viewer's timezone.
const longDay = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' })
const shortDay = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' })

function asDate(date: string): Date {
  return new Date(`${date}T00:00:00Z`)
}

/** `2026-11-05` → `Thu 5 Nov` */
export function formatDayLong(date: string): string {
  return longDay.format(asDate(date))
}

/** `2026-11-05` → `5 Nov` */
export function formatDayShort(date: string): string {
  return shortDay.format(asDate(date))
}

/** Whole-percent share of `part` in `total`; 0 when `total` is 0. */
export function percentOf(part: number, total: number): number {
  return total === 0 ? 0 : Math.round((part / total) * 100)
}

/**
 * Share label for a legend or tooltip: a whole percent, except that a part above 0
 * never reads "0%" and a part below the whole never reads "100%".
 */
export function shareLabel(part: number, total: number): string {
  const percent = percentOf(part, total)
  if (part > 0 && percent === 0) return '<1%'
  if (part < total && percent === 100) return '>99%'
  return `${percent}%`
}

/** Shared axis styling: recessive ink and no tick marks. */
export const axisTick = { fill: 'var(--color-fg-muted)', fontSize: 12 }
