import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { SlaBadge } from '../sla/SlaBadge'
import { formatPolicyDuration } from '../sla/formatPolicyDuration'
import { ActivityTimeline } from './ActivityTimeline'
import { PriorityBadge } from './PriorityBadge'
import { StatusBadge } from './StatusBadge'
import { TicketEditForm } from './TicketEditForm'
import { TicketStatusActions } from './TicketStatusActions'
import { getTicket } from './api'
import { priorityLabels } from './labels'
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
      <div className="card p-12 text-center text-fg-subtle">
        Loading ticket...
      </div>
    )
  }

  if (isError || !ticket) {
    return (
      <div role="alert" className="alert alert-danger p-6 text-center">
        <p>Failed to load ticket.</p>
        <button
          type="button"
          onClick={() => void refetch()}
          className="mt-3 btn btn-danger btn-sm"
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
          className="link-muted inline-flex items-center text-xs"
        >
          &larr; Back to tickets
        </Link>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center gap-3">
              <h1 className="font-mono text-2xl font-bold tracking-tight text-fg">
                {ticket.ticket_number}
              </h1>
              <StatusBadge status={ticket.status} />
              <PriorityBadge priority={ticket.priority} />
            </div>
            <p className="text-lg font-semibold text-fg">{ticket.subject}</p>
          </div>
        </div>
      </div>

      {/* Conflict alert */}
      {conflictMessage && (
        <div
          role="alert"
          className="alert alert-warning p-4 text-sm font-medium shadow-sm"
        >
          {conflictMessage}
        </div>
      )}

      {/* Main layout */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left 2 cols: Content / Edit form + Timeline */}
        <div className="space-y-6 lg:col-span-2">
          {isEditable ? (
            <div className="card p-6">
              <h2 className="mb-4 text-base font-semibold text-fg">Ticket Details</h2>
              <TicketEditForm
                ticket={ticket}
                onTicketUpdated={() => setConflictMessage(null)}
                onConflict={(_conflicted, msg) => setConflictMessage(msg)}
              />
            </div>
          ) : (
            <div className="card p-6">
              <h2 className="mb-4 text-base font-semibold text-fg">Description</h2>
              <div className="whitespace-pre-wrap text-sm text-fg-secondary leading-relaxed border-t border-border-subtle pt-4">
                {ticket.description}
              </div>
            </div>
          )}

          {/* Activity Timeline */}
          <div className="card p-6">
            <ActivityTimeline ticketNumber={ticket.ticket_number} />
          </div>
        </div>

        {/* Right col: Actions & Meta */}
        <div className="space-y-6">
          {/* Status actions card */}
          {ticket.permissions.allowed_statuses.length > 0 && (
            <div className="card p-6">
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-fg-subtle">
                Actions
              </h3>
              <TicketStatusActions
                ticket={ticket}
                onStatusChanged={() => setConflictMessage(null)}
                onConflict={(_conflicted, msg) => setConflictMessage(msg)}
              />
            </div>
          )}

          {/* SLA card */}
          {ticket.sla && (
            <div className="card p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-fg-subtle">
                  SLA
                </h3>
                <SlaBadge status={ticket.sla.status} />
              </div>
              <dl className="divide-y divide-border-subtle text-sm">
                <div className="py-2.5 flex justify-between">
                  <dt className="text-fg-subtle">Due</dt>
                  <dd className="font-medium text-fg">
                    {new Date(ticket.sla.due_at).toLocaleString()}
                  </dd>
                </div>
                <div className="py-2.5 flex justify-between">
                  <dt className="text-fg-subtle">Started</dt>
                  <dd className="text-fg-secondary">
                    {new Date(ticket.sla.started_at).toLocaleString()}
                  </dd>
                </div>
                <div className="py-2.5 flex justify-between">
                  <dt className="text-fg-subtle">Policy</dt>
                  <dd className="text-fg-secondary">
                    {`${priorityLabels[ticket.priority]}: ${formatPolicyDuration(ticket.sla.policy_minutes)}`}
                  </dd>
                </div>
              </dl>
            </div>
          )}

          {/* Metadata card */}
          <div className="card p-6">
            <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-fg-subtle">
              Information
            </h3>
            <dl className="divide-y divide-border-subtle text-sm">
              <div className="py-2.5 flex justify-between">
                <dt className="text-fg-subtle">Customer</dt>
                <dd className="font-medium text-fg text-right">
                  <div>{ticket.customer.name}</div>
                  <div className="text-xs text-fg-subtle">{ticket.customer.email}</div>
                </dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-fg-subtle">Created by</dt>
                <dd className="font-medium text-fg">{ticket.created_by.name}</dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-fg-subtle">Assigned</dt>
                <dd className="font-medium text-fg">
                  {ticket.assigned_agent ? ticket.assigned_agent.name : 'Unassigned'}
                </dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-fg-subtle">Category</dt>
                <dd className="font-medium text-fg">{ticket.category.name}</dd>
              </div>
              <div className="py-2.5 flex justify-between">
                <dt className="text-fg-subtle">Created</dt>
                <dd className="text-fg-secondary">{new Date(ticket.created_at).toLocaleString()}</dd>
              </div>
              {ticket.resolved_at && (
                <div className="py-2.5 flex justify-between">
                  <dt className="text-fg-subtle">Resolved</dt>
                  <dd className="text-fg-secondary">
                    {new Date(ticket.resolved_at).toLocaleString()}
                  </dd>
                </div>
              )}
              {ticket.closed_at && (
                <div className="py-2.5 flex justify-between">
                  <dt className="text-fg-subtle">Closed</dt>
                  <dd className="text-fg-secondary">{new Date(ticket.closed_at).toLocaleString()}</dd>
                </div>
              )}
            </dl>
          </div>
        </div>
      </div>
    </div>
  )
}
