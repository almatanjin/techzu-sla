import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  setFormError?: (message: string | null) => void,
): boolean {
  if (!axios.isAxiosError(error)) {
    setFormError?.('An unexpected error occurred. Please try again.')
    return false
  }

  const status = error.response?.status
  const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined

  if (status === 422 && data?.errors) {
    let hasFieldErrors = false
    for (const [field, messages] of Object.entries(data.errors)) {
      if (Array.isArray(messages) && messages.length > 0) {
        setError(field as Path<T>, {
          type: 'server',
          message: messages[0],
        })
        hasFieldErrors = true
      }
    }

    if (data.message && !hasFieldErrors) {
      setFormError?.(data.message)
    }

    return true
  }

  if (status === 429) {
    setFormError?.(data?.message ?? 'Too many requests. Please try again shortly.')
    return true
  }

  if (data?.message) {
    setFormError?.(data.message)
    return true
  }

  setFormError?.('A network error occurred. Please check your connection and try again.')
  return false
}

