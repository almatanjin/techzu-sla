import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { PriorityBadge } from '../tickets/PriorityBadge'
import { priorityLabels } from '../tickets/labels'
import type { TicketPriority } from '../tickets/types'
import { listSlaPolicies, updateSlaPolicy } from './api'
import { formatPolicyDuration } from './formatPolicyDuration'
import type { SlaPolicy } from './types'

type PolicyRowProps = {
  policy: SlaPolicy
  onUpdated: () => Promise<void>
}

function PolicyRow({ policy, onUpdated }: PolicyRowProps) {
  const apiClient = useApiClient()
  const priority = policy.priority as TicketPriority
  const label = priorityLabels[priority]

  const [hours, setHours] = useState<number>(Math.floor(policy.resolution_minutes / 60))
  const [minutes, setMinutes] = useState<number>(policy.resolution_minutes % 60)
  const [warningThreshold, setWarningThreshold] = useState<number>(policy.warning_threshold_percent)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [isSuccess, setIsSuccess] = useState(false)

  const mutation = useMutation({
    mutationFn: async () => {
      const resolutionMinutes = Number(hours) * 60 + Number(minutes)
      return updateSlaPolicy(apiClient, policy.priority, {
        resolution_minutes: resolutionMinutes,
        warning_threshold_percent: Number(warningThreshold),
      })
    },
    onSuccess: async () => {
      setErrors({})
      setGeneralError(null)
      setIsSuccess(true)
      setTimeout(() => setIsSuccess(false), 2500)
      await onUpdated()
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
        setGeneralError(anyErr.response?.data?.message ?? 'Failed to update SLA policy.')
      }
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    mutation.mutate()
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="p-4 border border-border rounded-lg bg-surface space-y-4"
    >
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle pb-3">
        <div className="flex items-center gap-3">
          <PriorityBadge priority={priority} />
          <span className="text-sm font-semibold text-fg">{label} Priority</span>
        </div>
        <div className="text-sm text-fg-secondary font-mono">
          Current: <span className="font-semibold text-fg">{formatPolicyDuration(policy.resolution_minutes)}</span>
          {' · '}
          <span>Warning at {policy.warning_threshold_percent}%</span>
        </div>
      </div>

      {generalError && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {generalError}
        </div>
      )}

      {isSuccess && (
        <div role="status" className="alert alert-success p-2 text-xs">
          Policy updated successfully.
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label
            htmlFor={`policy-${policy.priority}-hours`}
            className="block text-xs font-medium text-fg-secondary"
          >
            {label} resolution hours
          </label>
          <input
            id={`policy-${policy.priority}-hours`}
            type="number"
            min={0}
            max={720}
            value={hours}
            onChange={(e) => {
              setHours(Number(e.target.value))
              setErrors((prev) => {
                const next = { ...prev }
                delete next.resolution_minutes
                return next
              })
            }}
            className="mt-1 input input-sm"
          />
        </div>

        <div>
          <label
            htmlFor={`policy-${policy.priority}-minutes`}
            className="block text-xs font-medium text-fg-secondary"
          >
            {label} resolution minutes
          </label>
          <input
            id={`policy-${policy.priority}-minutes`}
            type="number"
            min={0}
            max={59}
            value={minutes}
            onChange={(e) => {
              setMinutes(Number(e.target.value))
              setErrors((prev) => {
                const next = { ...prev }
                delete next.resolution_minutes
                return next
              })
            }}
            className="mt-1 input input-sm"
          />
        </div>

        <div>
          <label
            htmlFor={`policy-${policy.priority}-threshold`}
            className="block text-xs font-medium text-fg-secondary"
          >
            {label} warning threshold percent
          </label>
          <div className="relative mt-1">
            <input
              id={`policy-${policy.priority}-threshold`}
              type="number"
              min={1}
              max={99}
              value={warningThreshold}
              onChange={(e) => {
                setWarningThreshold(Number(e.target.value))
                setErrors((prev) => {
                  const next = { ...prev }
                  delete next.warning_threshold_percent
                  return next
                })
              }}
              className="input input-sm pr-7"
            />
            <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-fg-subtle">
              %
            </span>
          </div>
        </div>
      </div>

      {errors.resolution_minutes && (
        <p role="alert" className="text-xs text-danger">
          {errors.resolution_minutes}
        </p>
      )}

      {errors.warning_threshold_percent && (
        <p role="alert" className="text-xs text-danger">
          {errors.warning_threshold_percent}
        </p>
      )}

      <div className="flex justify-end pt-2">
        <button
          type="submit"
          disabled={mutation.isPending}
          className="btn btn-sm btn-primary"
        >
          {mutation.isPending ? 'Saving...' : `Save ${label} Policy`}
        </button>
      </div>
    </form>
  )
}

export function SlaPoliciesSection() {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()

  const {
    data: policies,
    isLoading,
    isError,
    refetch,
  } = useQuery<SlaPolicy[]>({
    queryKey: ['admin', 'sla-policies'],
    queryFn: () => listSlaPolicies(apiClient),
  })

  const handleUpdated = async () => {
    await queryClient.invalidateQueries({ queryKey: ['admin', 'sla-policies'] })
  }

  if (isLoading) {
    return <div className="card p-6 text-sm text-fg-subtle text-center">Loading policies...</div>
  }

  if (isError || !policies) {
    return (
      <div role="alert" className="alert alert-danger p-4 text-center">
        <p>Failed to load SLA policies.</p>
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
      <div>
        <h2 className="text-lg font-semibold text-fg">SLA Policies</h2>
        <p className="mt-1 text-sm text-fg-muted">
          Configure resolution time and warning thresholds by ticket priority.
        </p>
      </div>

      <div className="space-y-4">
        {policies.map((policy) => (
          <PolicyRow key={policy.id} policy={policy} onUpdated={handleUpdated} />
        ))}
      </div>
    </div>
  )
}

