import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { getBusinessHours, updateBusinessHours } from './api'
import type { BusinessHourDay, BusinessHoursResponse } from './types'

const DAY_NAMES = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
]

type BusinessHoursSectionProps = {
  readOnly?: boolean
}

export function BusinessHoursSection({ readOnly }: BusinessHoursSectionProps = {}) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()

  const {
    data,
    isLoading,
    isError,
    refetch,
  } = useQuery<BusinessHoursResponse>({
    queryKey: ['admin', 'business-hours'],
    queryFn: () => getBusinessHours(apiClient),
  })

  const [editedDays, setEditedDays] = useState<BusinessHourDay[] | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [isSuccess, setIsSuccess] = useState(false)

  const days = editedDays ?? data?.days ?? []

  const mutation = useMutation({
    mutationFn: async () => {
      return updateBusinessHours(apiClient, days)
    },
    onSuccess: async (updated) => {
      setEditedDays(null)
      setErrors({})
      setGeneralError(null)
      setIsSuccess(true)
      setTimeout(() => setIsSuccess(false), 2500)
      queryClient.setQueryData(['admin', 'business-hours'], updated)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'business-hours'] })
    },
    onError: (err: unknown) => {
      setIsSuccess(false)
      const anyErr = err as {
        response?: {
          status?: number
          data?: { message?: string; errors?: Record<string, string[]> }
        }
      }

      if (anyErr.response?.status === 422 && anyErr.response.data?.errors) {
        const fieldErrors: Record<string, string> = {}
        for (const [key, msgs] of Object.entries(anyErr.response.data.errors)) {
          if (Array.isArray(msgs) && msgs.length > 0) {
            fieldErrors[key] = msgs[0]
          }
        }
        setErrors(fieldErrors)
        setGeneralError(null)
      } else {
        setGeneralError(anyErr.response?.data?.message ?? 'Failed to update business hours.')
      }
    },
  })

  const handleWorkingChange = (index: number, isWorking: boolean) => {
    setEditedDays((prev) => {
      const base = prev ?? data?.days ?? []
      const next = [...base]
      const current = next[index]
      next[index] = {
        ...current,
        is_working_day: isWorking,
        opens_at: isWorking ? current.opens_at || '09:00' : null,
        closes_at: isWorking ? current.closes_at || '18:00' : null,
      }
      return next
    })
  }

  const handleTimeChange = (index: number, field: 'opens_at' | 'closes_at', value: string) => {
    setEditedDays((prev) => {
      const base = prev ?? data?.days ?? []
      const next = [...base]
      next[index] = {
        ...next[index],
        [field]: value || null,
      }
      return next
    })
    setErrors((prev) => {
      const next = { ...prev }
      delete next[`days.${index}.${field}`]
      return next
    })
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    mutation.mutate()
  }

  if (isLoading) {
    return (
      <div className="card p-6 text-sm text-fg-subtle text-center">
        Loading business hours...
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div role="alert" className="alert alert-danger p-4 text-center">
        <p>Failed to load business hours.</p>
        <button
          type="button"
          onClick={() => void refetch()}
          className="mt-2 btn btn-danger btn-xs"
        >
          Retry
        </button>
      </div>
    )
  }

  return (
    <div className="card p-6 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-4">
        <div>
          <h2 className="text-lg font-semibold text-fg">Business Hours</h2>
          <p className="mt-1 text-sm text-fg-muted">
            Define support operating hours per day of the week.
          </p>
        </div>
        <div className="text-xs font-mono px-3 py-1 bg-surface-secondary border border-border rounded-md text-fg-secondary font-semibold">
          Timezone: {data.timezone}
        </div>
      </div>

      {generalError && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {generalError}
        </div>
      )}

      {errors.days && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {errors.days}
        </div>
      )}

      {isSuccess && (
        <div role="status" className="alert alert-success p-2 text-xs">
          Business hours updated successfully.
        </div>
      )}

      {readOnly ? (
        <div className="divide-y divide-border-subtle border border-border rounded-lg overflow-hidden bg-surface">
          {days.map((day) => {
            const dayName = DAY_NAMES[day.day_of_week]
            return (
              <div key={day.day_of_week} className="p-3.5 flex items-center justify-between gap-4 text-sm">
                <span className="font-medium text-fg w-36">{dayName}</span>
                <span className={day.is_working_day ? 'text-fg font-mono' : 'text-fg-subtle italic'}>
                  {day.is_working_day ? `${day.opens_at} to ${day.closes_at}` : 'Closed'}
                </span>
              </div>
            )
          })}
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="divide-y divide-border-subtle border border-border rounded-lg overflow-hidden bg-surface">
            {days.map((day, index) => {
              const dayName = DAY_NAMES[day.day_of_week]
              const opensError = errors[`days.${index}.opens_at`]
              const closesError = errors[`days.${index}.closes_at`]

              return (
                <div key={day.day_of_week} className="p-3.5 space-y-2">
                  <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3 w-36">
                      <input
                        id={`day-${day.day_of_week}-working`}
                        type="checkbox"
                        aria-label={`${dayName} working day`}
                        checked={day.is_working_day}
                        onChange={(e) => handleWorkingChange(index, e.target.checked)}
                        className="checkbox checkbox-sm"
                      />
                      <label
                        htmlFor={`day-${day.day_of_week}-working`}
                        className="text-sm font-medium text-fg cursor-pointer select-none"
                      >
                        {dayName}
                      </label>
                    </div>

                    <div className="flex items-center gap-2 flex-1 min-w-0 sm:min-w-[240px]">
                      <div className="flex-1">
                        <label
                          htmlFor={`day-${day.day_of_week}-opens`}
                          className="sr-only"
                        >
                          {dayName} opening time
                        </label>
                        <input
                          id={`day-${day.day_of_week}-opens`}
                          type="time"
                          aria-label={`${dayName} opening time`}
                          disabled={!day.is_working_day}
                          value={day.opens_at ?? ''}
                          onChange={(e) => handleTimeChange(index, 'opens_at', e.target.value)}
                          className="input input-sm w-full"
                        />
                      </div>
                      <span className="text-xs text-fg-subtle">to</span>
                      <div className="flex-1">
                        <label
                          htmlFor={`day-${day.day_of_week}-closes`}
                          className="sr-only"
                        >
                          {dayName} closing time
                        </label>
                        <input
                          id={`day-${day.day_of_week}-closes`}
                          type="time"
                          aria-label={`${dayName} closing time`}
                          disabled={!day.is_working_day}
                          value={day.closes_at ?? ''}
                          onChange={(e) => handleTimeChange(index, 'closes_at', e.target.value)}
                          className="input input-sm w-full"
                        />
                      </div>
                    </div>
                  </div>

                  {opensError && (
                    <p role="alert" className="text-xs text-danger pl-8">
                      {opensError}
                    </p>
                  )}
                  {closesError && (
                    <p role="alert" className="text-xs text-danger pl-8">
                      {closesError}
                    </p>
                  )}
                </div>
              )
            })}
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={mutation.isPending}
              className="btn btn-sm btn-primary"
            >
              {mutation.isPending ? 'Saving...' : 'Save Business Hours'}
            </button>
          </div>
        </form>
      )}
    </div>
  )
}
