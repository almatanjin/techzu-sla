let wasSessionEnded = false

export function markSessionEnded(): void {
  wasSessionEnded = true
}

export function consumeSessionEnded(): boolean {
  const value = wasSessionEnded
  wasSessionEnded = false
  return value
}

