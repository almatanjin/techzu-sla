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

  const getButtonStyles = (status: TicketStatus): string => {
    switch (status) {
      case 'open':
        return 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500'
      case 'in_progress':
        return 'bg-blue-600 text-white hover:bg-blue-500 focus:ring-blue-500'
      case 'waiting_for_customer':
        return 'bg-amber-600 text-white hover:bg-amber-500 focus:ring-amber-500'
      case 'resolved':
        return 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500'
      case 'closed':
        return 'bg-slate-700 text-white hover:bg-slate-600 focus:ring-slate-500'
      default:
        return 'bg-slate-600 text-white hover:bg-slate-500 focus:ring-slate-500'
    }
  }

  return (
    <div className="space-y-3">
      {actionError && (
        <p role="alert" className="text-xs text-rose-600">
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
            className={`rounded-md px-3 py-1.5 text-xs font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 ${getButtonStyles(
              status,
            )}`}
          >
            {getActionLabel(status)}
          </button>
        ))}
      </div>
    </div>
  )
}

