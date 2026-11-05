import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { changeTicketStatus } from './api'
import type { Ticket, TicketStatus } from './types'

type Props = {
  ticket: Ticket
  onStatusChanged?: (ticket: Ticket) => void
  onConflict?: (conflictTicket: Ticket, message: string) => void
}

const statusButtonVariants: Record<TicketStatus, string> = {
  open: 'btn-success',
  resolved: 'btn-success',
  in_progress: 'btn-primary',
  waiting_for_customer: 'btn-warning',
  closed: 'btn-secondary',
}

export function TicketStatusActions({ ticket, onStatusChanged, onConflict }: Props) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const [actionError, setActionError] = useState<string | null>(null)

  const allowedStatuses = ticket.permissions.allowed_statuses

  const mutation = useMutation({
    mutationFn: async (targetStatus: TicketStatus) => {
      return changeTicketStatus(apiClient, ticket.ticket_number, {
        status: targetStatus,
        lock_version: ticket.lock_version,
      })
    },
    onSuccess: async (updated) => {
      setActionError(null)
      queryClient.setQueryData(['tickets', updated.ticket_number], updated)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tickets'] }),
        queryClient.invalidateQueries({
          queryKey: ['tickets', updated.ticket_number, 'activities'],
        }),
      ])
      onStatusChanged?.(updated)
    },
    onError: (err: unknown) => {
      // Check for 409 conflict
      const anyErr = err as { response?: { status?: number; data?: { message?: string; ticket?: Ticket } } }
      if (anyErr?.response?.status === 409 && anyErr.response.data?.ticket) {
        const conflictTicket = anyErr.response.data.ticket
        const message = anyErr.response.data.message ?? 'This ticket was changed by someone else. The latest version is now shown.'
        queryClient.setQueryData(['tickets', conflictTicket.ticket_number], conflictTicket)
        onConflict?.(conflictTicket, message)
        return
      }

      applyApiErrors(err, () => {}, setActionError)
    },
  })

  if (!allowedStatuses || allowedStatuses.length === 0) {
    return null
  }

  const getActionLabel = (status: TicketStatus): string => {
    if (status === 'open') {
      return ticket.status === 'resolved' || ticket.status === 'closed' ? 'Reopen Ticket' : 'Open'
    }
    if (status === 'in_progress') {
      return ticket.status === 'resolved' ? 'Reopen Ticket' : 'Start Work'
    }
    if (status === 'waiting_for_customer') {
      return 'Waiting for Customer'
    }
    if (status === 'resolved') {
      return 'Resolve'
    }
    if (status === 'closed') {
      return 'Close'
    }
    return status
  }

  return (
    <div className="space-y-3">
      {actionError && (
        <p role="alert" className="text-xs text-danger">
          {actionError}
        </p>
      )}

      <div className="flex flex-wrap items-center gap-2">
        {allowedStatuses.map((status) => (
          <button
            key={status}
            type="button"
            disabled={mutation.isPending}
            onClick={() => mutation.mutate(status)}
            className={`btn btn-sm ${statusButtonVariants[status] ?? 'btn-secondary'}`}
          >
            {getActionLabel(status)}
          </button>
        ))}
      </div>
    </div>
  )
}
