import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import type { Ticket } from '../tickets/types'
import { AgentPicker } from './AgentPicker'
import { claimTicket, releaseTicket } from './api'
import type { ConflictResponseData } from './types'

type Props = {
  ticket: Ticket
  onTicketUpdated?: (ticket: Ticket) => void
  onConflict?: (conflictTicket: Ticket, message: string) => void
}

export function AssignmentCard({ ticket, onTicketUpdated, onConflict }: Props) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const [actionError, setActionError] = useState<string | null>(null)

  const handleConflict = (err: unknown): boolean => {
    const anyErr = err as { response?: { status?: number; data?: ConflictResponseData } }
    if (anyErr?.response?.status === 409 && anyErr.response.data?.ticket) {
      const conflictTicket = anyErr.response.data.ticket
      const message =
        anyErr.response.data.message ??
        'This ticket was changed by someone else. The latest version is now shown.'
      queryClient.setQueryData(['tickets', conflictTicket.ticket_number], conflictTicket)
      onConflict?.(conflictTicket, message)
      return true
    }
    return false
  }

  const claimMutation = useMutation({
    mutationFn: async () => claimTicket(apiClient, ticket.ticket_number),
    onSuccess: async (updated) => {
      setActionError(null)
      queryClient.setQueryData(['tickets', updated.ticket_number], updated)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tickets'] }),
        queryClient.invalidateQueries({
          queryKey: ['tickets', updated.ticket_number, 'activities'],
        }),
      ])
      onTicketUpdated?.(updated)
    },
    onError: (err: unknown) => {
      if (handleConflict(err)) return
      applyApiErrors(err, () => {}, setActionError)
    },
  })

  const releaseMutation = useMutation({
    mutationFn: async () => releaseTicket(apiClient, ticket.ticket_number),
    onSuccess: async (updated) => {
      setActionError(null)
      queryClient.setQueryData(['tickets', updated.ticket_number], updated)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tickets'] }),
        queryClient.invalidateQueries({
          queryKey: ['tickets', updated.ticket_number, 'activities'],
        }),
      ])
      onTicketUpdated?.(updated)
    },
    onError: (err: unknown) => {
      if (handleConflict(err)) return
      applyApiErrors(err, () => {}, setActionError)
    },
  })

  return (
    <div className="card p-6">
      <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-fg-subtle">
        Assignment
      </h3>

      {actionError && (
        <p role="alert" className="mb-3 text-xs text-danger">
          {actionError}
        </p>
      )}

      <div className="flex items-center justify-between mb-3 text-sm">
        <span className="text-fg-subtle">Assignee</span>
        <span className="font-medium text-fg">
          {ticket.assigned_agent ? ticket.assigned_agent.name : 'Unassigned'}
        </span>
      </div>

      <div className="space-y-3">
        {ticket.permissions.can_claim && (
          <button
            type="button"
            disabled={claimMutation.isPending}
            onClick={() => claimMutation.mutate()}
            className="btn btn-sm btn-primary w-full"
          >
            Claim
          </button>
        )}

        {ticket.permissions.can_release && (
          <button
            type="button"
            disabled={releaseMutation.isPending}
            onClick={() => releaseMutation.mutate()}
            className="btn btn-sm btn-secondary w-full"
          >
            Release
          </button>
        )}
      </div>

      {ticket.permissions.can_assign && (
        <AgentPicker
          ticket={ticket}
          onTicketUpdated={onTicketUpdated}
          onConflict={onConflict}
        />
      )}
    </div>
  )
}
