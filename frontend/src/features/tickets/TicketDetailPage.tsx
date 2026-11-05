import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { ActivityTimeline } from './ActivityTimeline'
import { PriorityBadge } from './PriorityBadge'
import { StatusBadge } from './StatusBadge'
import { TicketEditForm } from './TicketEditForm'
import { TicketStatusActions } from './TicketStatusActions'
import { getTicket } from './api'
import type { Ticket } from './types'

export function TicketDetailPage() {
  const { ticketNumber } = useParams<{ ticketNumber: string }>()
  const apiClient = useApiClient()
  const [conflictMessage, setConflictMessage] = useState<string | null>(null)

  const {
    data: ticket,
    isLoading,
    isError,
    refetch,
  } = useQuery<Ticket>({
    queryKey: ['tickets', ticketNumber],
    queryFn: () => getTicket(apiClient, ticketNumber!),
    enabled: Boolean(ticketNumber),
  })

  if (isLoading) {
    return (
      <div className="rounded-lg border border-slate-200 bg-white p-12 text-center text-slate-500">
        Loading ticket...
      </div>
    )
  }

  if (isError || !ticket) {
    return (
      <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-center text-rose-800">
        <p>Failed to load ticket.</p>
        <button
          type="button"
          onClick={() => void refetch()}
          className="mt-3 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500"
        >
          Retry
        </button>
      </div>
    )
  }

  const isEditable =
    ticket.permissions.can_edit_content || ticket.permissions.can_edit_classification

  return (
    <div className="space-y-6">
      {/* Header & Breadcrumb */}
      <div>
        <Link
          to="/tickets"
          className="inline-flex items-center text-xs font-medium text-slate-500 hover:text-slate-700 hover:underline"
        >
          &larr; Back to tickets
        </Link>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center gap-3">
              <h1 className="font-mono text-2xl font-bold tracking-tight text-slate-900">
                {ticket.ticket_number}
              </h1>
              <StatusBadge status={ticket.status} />
              <PriorityBadge priority={ticket.priority} />
            </div>
            <p className="text-lg font-semibold text-slate-900">{ticket.subject}</p>
          </div>
        </div>
      </div>

      {/* Conflict alert */}
      {conflictMessage && (
        <div
          role="alert"
          className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-900 shadow-sm"
        >
          {conflictMessage}
        </div>
      )}

      {/* Main layout */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left 2 cols: Content / Edit form + Timeline */}
        <div className="space-y-6 lg:col-span-2">
          {isEditable ? (
            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-base font-semibold text-slate-900">Ticket Details</h2>
              <TicketEditForm
                ticket={ticket}
                onTicketUpdated={() => setConflictMessage(null)}
                onConflict={(_conflicted, msg) => setConflictMessage(msg)}
              />
            </div>
          ) : (
            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-base font-semibold text-slate-900">Description</h2>
              <div className="whitespace-pre-wrap text-sm text-slate-700 leading-relaxed border-t border-slate-100 pt-4">
                {ticket.description}
              </div>
            </div>
          )}

          {/* Activity Timeline */}
          <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <ActivityTimeline ticketNumber={ticket.ticket_number} />
          </div>
        </div>

        {/* Right col: Actions & Meta */}
        <div className="space-y-6">
          {/* Status actions card */}
          {ticket.permissions.allowed_statuses.length > 0 && (
            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">
                Actions
              </h3>
              <TicketStatusActions
                ticket={ticket}
                onStatusChanged={() => setConflictMessage(null)}
                onConflict={(_conflicted, msg) => setConflictMessage(msg)}
              />
            </div>
          )}

          {/* Metadata card */}
          <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">
              Information
            </h3>
            <dl className="divide-y divide-slate-100 text-sm">
              <div className="py-2.5 flex justify-between">
                <dt className="text-slate-500">Customer</dt>
                <dd className="font-medium text-slate-900 text-right">
                  <div>{ticket.customer.name}</div>
                  <div className="text-xs text-slate-500">{ticket.customer.email}</div>
                </dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-slate-500">Created by</dt>
                <dd className="font-medium text-slate-900">{ticket.created_by.name}</dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-slate-500">Assigned</dt>
                <dd className="font-medium text-slate-900">
                  {ticket.assigned_agent ? ticket.assigned_agent.name : 'Unassigned'}
                </dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-slate-500">Category</dt>
                <dd className="font-medium text-slate-900">{ticket.category.name}</dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-slate-500">Created</dt>
                <dd className="text-slate-700">{new Date(ticket.created_at).toLocaleString()}</dd>
              </div>
              {ticket.resolved_at && (
                <div className="py-2.5 flex justify-between">
                  <dt className="text-slate-500">Resolved</dt>
                  <dd className="text-slate-700">
                    {new Date(ticket.resolved_at).toLocaleString()}
                  </dd>
                </div>
              )}
              {ticket.closed_at && (
                <div className="py-2.5 flex justify-between">
                  <dt className="text-slate-500">Closed</dt>
                  <dd className="text-slate-700">{new Date(ticket.closed_at).toLocaleString()}</dd>
                </div>
              )}
            </dl>
          </div>
        </div>
      </div>
    </div>
  )
}
