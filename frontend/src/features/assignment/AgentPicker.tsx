import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import type { Ticket } from '../tickets/types'
import { assignTicket, listAgents } from './api'
import type { AgentCandidate, ConflictResponseData } from './types'

type Props = {
  ticket: Ticket
  onTicketUpdated?: (ticket: Ticket) => void
  onConflict?: (conflictTicket: Ticket, message: string) => void
}

export function AgentPicker({ ticket, onTicketUpdated, onConflict }: Props) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const [selectedAgentId, setSelectedAgentId] = useState<string>('')
  const [actionError, setActionError] = useState<string | null>(null)

  const { data: agents = [], isLoading } = useQuery<AgentCandidate[]>({
    queryKey: ['admin', 'agents'],
    queryFn: () => listAgents(apiClient),
  })

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

  const mutation = useMutation({
    mutationFn: async (agentId: number | null) => {
      return assignTicket(apiClient, ticket.ticket_number, {
        agent_id: agentId,
        lock_version: ticket.lock_version,
      })
    },
    onSuccess: async (updated) => {
      setActionError(null)
      setSelectedAgentId('')
      queryClient.setQueryData(['tickets', updated.ticket_number], updated)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tickets'] }),
        queryClient.invalidateQueries({
          queryKey: ['tickets', updated.ticket_number, 'activities'],
        }),
        queryClient.invalidateQueries({ queryKey: ['admin', 'agents'] }),
      ])
      onTicketUpdated?.(updated)
    },
    onError: (err: unknown) => {
      if (handleConflict(err)) return
      applyApiErrors(err, () => {}, setActionError)
    },
  })

  return (
    <div className="mt-4 pt-4 border-t border-border-subtle space-y-3">
      {actionError && (
        <p role="alert" className="text-xs text-danger">
          {actionError}
        </p>
      )}

      <div>
        <label htmlFor="assign-agent-select" className="sr-only">
          Assign agent
        </label>
        <select
          id="assign-agent-select"
          aria-label="Assign agent"
          value={selectedAgentId}
          disabled={isLoading || mutation.isPending}
          onChange={(e) => setSelectedAgentId(e.target.value)}
          className="input input-sm w-full"
        >
          <option value="">Select an agent</option>
          {agents.map((agent) => (
            <option key={agent.id} value={agent.id}>
              {agent.name} ({agent.is_available ? 'Available' : 'Unavailable'}, {agent.active_ticket_count} active)
            </option>
          ))}
        </select>
      </div>

      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled={!selectedAgentId || mutation.isPending}
          onClick={() => mutation.mutate(Number(selectedAgentId))}
          className="btn btn-sm btn-primary flex-1"
        >
          Assign
        </button>
        {ticket.assigned_agent !== null && (
          <button
            type="button"
            disabled={mutation.isPending}
            onClick={() => mutation.mutate(null)}
            className="btn btn-sm btn-outline"
          >
            Unassign
          </button>
        )}
      </div>
    </div>
  )
}

