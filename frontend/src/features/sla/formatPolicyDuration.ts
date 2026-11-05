export function formatPolicyDuration(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60

  if (hours > 0 && remainingMinutes === 0) {
    return `${hours} business hour${hours === 1 ? '' : 's'}`
  }

  if (hours > 0 && remainingMinutes > 0) {
    return `${hours} h ${remainingMinutes} min`
  }

  return `${remainingMinutes} min`
}

