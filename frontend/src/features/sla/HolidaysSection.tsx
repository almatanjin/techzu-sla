import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { createHoliday, deleteHoliday, listHolidays } from './api'
import type { Holiday } from './types'

type HolidaysSectionProps = {
  readOnly?: boolean
}

export function HolidaysSection({ readOnly }: HolidaysSectionProps = {}) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()

  const [selectedYear, setSelectedYear] = useState<number>(() => new Date().getFullYear())
  const [date, setDate] = useState('')
  const [name, setName] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [isSuccess, setIsSuccess] = useState(false)

  const {
    data: holidays,
    isLoading,
    isError,
    refetch,
  } = useQuery<Holiday[]>({
    queryKey: ['admin', 'holidays', selectedYear],
    queryFn: () => listHolidays(apiClient, selectedYear),
  })

  const addMutation = useMutation({
    mutationFn: async () => {
      return createHoliday(apiClient, { date, name: name.trim() })
    },
    onSuccess: async () => {
      setDate('')
      setName('')
      setErrors({})
      setGeneralError(null)
      setIsSuccess(true)
      setTimeout(() => setIsSuccess(false), 2500)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'holidays'] })
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
        setGeneralError(anyErr.response?.data?.message ?? 'Failed to add holiday.')
      }
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      return deleteHoliday(apiClient, id)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'holidays'] })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setGeneralError(anyErr.response?.data?.message ?? 'Failed to delete holiday.')
    },
  })

  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!date) {
      setErrors((prev) => ({ ...prev, date: 'The date field is required.' }))
      return
    }
    if (!name.trim()) {
      setErrors((prev) => ({ ...prev, name: 'The name field is required.' }))
      return
    }
    addMutation.mutate()
  }

  return (
    <div className="card p-6 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4">
        <div>
          <h2 className="text-lg font-semibold text-fg">Holidays</h2>
          <p className="mt-1 text-sm text-fg-muted">
            Manage organization holidays that pause SLA business minute counting.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <label htmlFor="holiday-year-select" className="text-xs font-medium text-fg-secondary">
            Year
          </label>
          <input
            id="holiday-year-select"
            type="number"
            min={2000}
            max={2100}
            value={selectedYear}
            onChange={(e) => setSelectedYear(Number(e.target.value))}
            className="input input-sm w-24 text-center font-mono"
          />
        </div>
      </div>

      {generalError && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {generalError}
        </div>
      )}

      {isSuccess && (
        <div role="status" className="alert alert-success p-2 text-xs">
          Holiday added successfully.
        </div>
      )}

      {/* Add Holiday Form */}
      {!readOnly && (
        <form
          onSubmit={handleAddSubmit}
          className="p-4 border border-border rounded-lg bg-surface space-y-4"
        >
          <h3 className="text-sm font-semibold text-fg">Add Holiday</h3>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="holiday-date" className="block text-xs font-medium text-fg-secondary">
                Holiday date
              </label>
              <input
                id="holiday-date"
                type="date"
                aria-label="Holiday date"
                value={date}
                onChange={(e) => {
                  setDate(e.target.value)
                  setErrors((prev) => {
                    const next = { ...prev }
                    delete next.date
                    return next
                  })
                }}
                className="mt-1 input input-sm w-full font-mono"
              />
              {errors.date && (
                <p role="alert" className="mt-1 text-xs text-danger">
                  {errors.date}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="holiday-name" className="block text-xs font-medium text-fg-secondary">
                Holiday name
              </label>
              <input
                id="holiday-name"
                type="text"
                aria-label="Holiday name"
                placeholder="e.g. New Year's Day"
                value={name}
                onChange={(e) => {
                  setName(e.target.value)
                  setErrors((prev) => {
                    const next = { ...prev }
                    delete next.name
                    return next
                  })
                }}
                className="mt-1 input input-sm w-full"
              />
              {errors.name && (
                <p role="alert" className="mt-1 text-xs text-danger">
                  {errors.name}
                </p>
              )}
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={addMutation.isPending}
              className="btn btn-sm btn-primary"
            >
              {addMutation.isPending ? 'Adding...' : 'Add Holiday'}
            </button>
          </div>
        </form>
      )}

      {/* Holidays List */}
      <div>
        {isLoading ? (
          <div className="py-8 text-center text-sm text-fg-subtle">Loading holidays...</div>
        ) : isError ? (
          <div role="alert" className="alert alert-danger p-4 text-center text-sm">
            Failed to load holidays.
            <button
              type="button"
              onClick={() => void refetch()}
              className="ml-3 btn btn-danger btn-xs"
            >
              Retry
            </button>
          </div>
        ) : holidays && holidays.length > 0 ? (
          <div className="overflow-x-auto border border-border rounded-lg bg-surface">
            <table className="min-w-full divide-y divide-border-subtle text-sm">
              <thead className="bg-surface-secondary">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fg-subtle">
                    Date
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fg-subtle">
                    Name
                  </th>
                  {!readOnly && (
                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-fg-subtle">
                      Actions
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-border-subtle">
                {holidays.map((holiday) => (
                  <tr key={holiday.id} className="hover:bg-surface-hover">
                    <td className="px-4 py-3 font-mono text-fg">{holiday.date}</td>
                    <td className="px-4 py-3 font-medium text-fg">{holiday.name}</td>
                    {!readOnly && (
                      <td className="px-4 py-3 text-right">
                        <button
                          type="button"
                          aria-label={`Delete ${holiday.name}`}
                          disabled={deleteMutation.isPending}
                          onClick={() => deleteMutation.mutate(holiday.id)}
                          className="btn btn-xs btn-outline text-danger hover:bg-danger hover:text-white"
                        >
                          Delete
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="py-8 text-center text-sm text-fg-subtle border border-dashed border-border rounded-lg">
            No holidays registered for {selectedYear}.
          </div>
        )}
      </div>
    </div>
  )
}
