import { keepPreviousData, useQuery } from '@tanstack/react-query'
import axios from 'axios'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { useAuth } from '../auth/useAuth'
import { SlaBadge } from '../sla/SlaBadge'
import { PriorityBadge } from './PriorityBadge'
import { StatusBadge } from './StatusBadge'
import { TicketFilterBar } from './TicketFilterBar'
import { listTickets } from './api'
import { filtersFromParams, paramsFromFilters, type TicketListFilters } from './listFilters'
import type { PaginatedTicketsResponse, TicketView } from './types'

export function TicketsListPage() {
  const { user } = useAuth()
  const apiClient = useApiClient()
  const role = user?.role ?? 'customer'

  const [searchParams, setSearchParams] = useSearchParams()
  const filters = filtersFromParams(searchParams)
  const currentView: TicketView = filters.view === 'closed' ? 'closed' : 'active'

  const [clearedSearchTerm, setClearedSearchTerm] = useState<string | null>(null)

  const queryResult = useQuery<PaginatedTicketsResponse>({
    queryKey: ['tickets', filters],
    queryFn: () =>
      listTickets(apiClient, {
        view: filters.view,
        status: filters.status,
        priority: filters.priority,
        categoryId: filters.categoryId,
        search: filters.search,
        assigned: filters.assigned,
        customer_id: filters.customerId,
        created_from: filters.createdFrom,
        created_to: filters.createdTo,
        sla_status: filters.slaStatus,
        sort: filters.sort,
        per_page: filters.perPage,
        page: filters.page,
      }),
    placeholderData: keepPreviousData,
    retry: false,
  })

  const { refetch } = queryResult
  const [lastSuccessfulData, setLastSuccessfulData] = useState<PaginatedTicketsResponse | null>(null)

  if (queryResult.data && queryResult.data !== lastSuccessfulData) {
    setLastSuccessfulData(queryResult.data)
  }

  const data = queryResult.data ?? lastSuccessfulData
  const isLoading = queryResult.isLoading && !data

  const rawSearchError =
    queryResult.error && axios.isAxiosError(queryResult.error)
      ? (queryResult.error.response?.data as { errors?: { search?: string[] } })?.errors?.search?.[0]
      : undefined

  const searchError =
    filters.search && filters.search === clearedSearchTerm ? undefined : rawSearchError

  const handleFilterChange = (newFilters: TicketListFilters) => {
    setSearchParams(paramsFromFilters(newFilters))
  }

  const handleTabChange = (view: TicketView) => {
    const next: TicketListFilters = { ...filters, view, page: undefined }
    const st = next.status
    if (view === 'active' && (st === 'resolved' || st === 'closed')) {
      next.status = undefined
    } else if (
      view === 'closed' &&
      (st === 'open' || st === 'in_progress' || st === 'waiting_for_customer')
    ) {
      next.status = undefined
    }
    setSearchParams(paramsFromFilters(next))
  }

  const handlePageChange = (newPage: number) => {
    setSearchParams(
      paramsFromFilters({
        ...filters,
        page: newPage > 1 ? newPage : undefined,
      }),
    )
  }

  const isGeneralError = queryResult.isError && !searchError

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-fg">Tickets</h1>
          <p className="mt-1 text-sm text-fg-muted">
            Track and manage customer support requests.
          </p>
        </div>
        <Link to="/tickets/new" className="btn btn-primary">
          New Ticket
        </Link>
      </div>

      {/* Tabs */}
      <div className="border-b border-border">
        <nav className="-mb-px flex space-x-8" aria-label="Tabs">
          <button
            type="button"
            data-active={currentView === 'active'}
            onClick={() => handleTabChange('active')}
            className={`tab ${currentView === 'active' ? 'tab-active' : ''}`.trim()}
          >
            Active
          </button>
          <button
            type="button"
            data-active={currentView === 'closed'}
            onClick={() => handleTabChange('closed')}
            className={`tab ${currentView === 'closed' ? 'tab-active' : ''}`.trim()}
          >
            Resolved & Closed
          </button>
        </nav>
      </div>

      {/* Filter controls */}
      <TicketFilterBar
        filters={filters}
        role={role}
        onChange={handleFilterChange}
        searchError={searchError}
        onClearSearchError={() => setClearedSearchTerm(filters.search ?? '')}
      />

      {/* Content states */}
      {isLoading && (
        <div className="card p-12 text-center text-fg-subtle">
          Loading tickets...
        </div>
      )}

      {isGeneralError && (
        <div role="alert" className="alert alert-danger p-6 text-center">
          <p>Failed to load tickets.</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-3 btn btn-danger btn-sm"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isGeneralError && data && (
        <div className="overflow-hidden card">
          {data.data.length === 0 ? (
            <div className="p-12 text-center text-fg-subtle">No tickets found.</div>
          ) : (
            <>
              {/* Desktop table */}
              <div className="hidden md:block overflow-x-auto">
                <table className="min-w-full divide-y divide-border text-left text-sm">
                  <thead className="bg-surface-muted text-fg-secondary">
                    <tr>
                      <th className="px-6 py-3 font-semibold">Ticket</th>
                      <th className="px-6 py-3 font-semibold">Subject</th>
                      <th className="px-6 py-3 font-semibold">Status</th>
                      <th className="px-6 py-3 font-semibold">Priority</th>
                      {data.data.some((t) => Boolean(t.sla)) && (
                        <th className="px-6 py-3 font-semibold">SLA</th>
                      )}
                      <th className="px-6 py-3 font-semibold">Category</th>
                      <th className="px-6 py-3 font-semibold">Customer</th>
                      <th className="px-6 py-3 font-semibold">Assigned</th>
                      <th className="px-6 py-3 font-semibold">Created</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border bg-surface">
                    {data.data.map((ticket) => (
                      <tr key={ticket.ticket_number} className="hover:bg-surface-muted/50">
                        <td className="px-6 py-4 font-mono font-medium">
                          <Link
                            to={`/tickets/${ticket.ticket_number}`}
                            className="link font-semibold"
                          >
                            {ticket.ticket_number}
                          </Link>
                        </td>
                        <td className="px-6 py-4 font-medium text-fg">
                          <Link
                            to={`/tickets/${ticket.ticket_number}`}
                            className="hover:text-accent"
                          >
                            {ticket.subject}
                          </Link>
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge status={ticket.status} />
                        </td>
                        <td className="px-6 py-4">
                          <PriorityBadge priority={ticket.priority} />
                        </td>
                        {data.data.some((t) => Boolean(t.sla)) && (
                          <td className="px-6 py-4">
                            {ticket.sla ? (
                              <div className="space-y-1">
                                <SlaBadge
                                  status={ticket.sla.status}
                                  escalated={Boolean(ticket.sla.escalated_at)}
                                />
                                <div className="text-xs text-fg-subtle">
                                  {new Date(ticket.sla.due_at).toLocaleString()}
                                </div>
                              </div>
                            ) : null}
                          </td>
                        )}
                        <td className="px-6 py-4 text-fg-muted">{ticket.category.name}</td>
                        <td className="px-6 py-4 text-fg-muted">{ticket.customer.name}</td>
                        <td className="px-6 py-4 text-fg-muted">
                          {ticket.assigned_agent ? (
                            ticket.assigned_agent.name
                          ) : (
                            <span className="text-fg-placeholder">Unassigned</span>
                          )}
                        </td>
                        <td className="px-6 py-4 text-fg-subtle whitespace-nowrap">
                          {new Date(ticket.created_at).toLocaleDateString()}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Mobile cards view */}
              <div className="divide-y divide-border md:hidden">
                {data.data.map((ticket) => (
                  <div key={ticket.ticket_number} className="space-y-2 p-4">
                    <div className="flex items-center justify-between gap-2">
                      <Link
                        to={`/tickets/${ticket.ticket_number}`}
                        className="link font-mono text-sm font-semibold"
                      >
                        {ticket.ticket_number}
                      </Link>
                      <div className="flex items-center gap-1.5">
                        <StatusBadge status={ticket.status} />
                        <PriorityBadge priority={ticket.priority} />
                      </div>
                    </div>
                    <div>
                      <Link
                        to={`/tickets/${ticket.ticket_number}`}
                        className="font-medium text-fg hover:text-accent"
                      >
                        {ticket.subject}
                      </Link>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-fg-subtle">
                      <span>Category: {ticket.category.name}</span>
                      <span>Customer: {ticket.customer.name}</span>
                      <span>
                        Assigned:{' '}
                        {ticket.assigned_agent ? ticket.assigned_agent.name : 'Unassigned'}
                      </span>
                      <span>{new Date(ticket.created_at).toLocaleDateString()}</span>
                    </div>
                    {ticket.sla && (
                      <div className="flex items-center gap-2 pt-1 text-xs">
                        <SlaBadge
                          status={ticket.sla.status}
                          escalated={Boolean(ticket.sla.escalated_at)}
                        />
                        <span className="text-fg-subtle">
                          Due: {new Date(ticket.sla.due_at).toLocaleString()}
                        </span>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          )}

          {/* Footer with rows per page and pagination */}
          <div className="flex flex-wrap items-center justify-between gap-4 border-t border-border bg-surface px-4 py-3 sm:px-6">
            <div className="flex items-center gap-2">
              <label htmlFor="per-page-select" className="text-xs font-medium text-fg-secondary">
                Rows per page
              </label>
              <select
                id="per-page-select"
                value={filters.perPage ?? 20}
                onChange={(e) => {
                  setSearchParams(
                    paramsFromFilters({
                      ...filters,
                      perPage: Number(e.target.value),
                      page: undefined,
                    }),
                  )
                }}
                className="input input-sm py-1"
              >
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            {data.meta && data.meta.last_page > 1 && (
              <div className="flex items-center gap-4">
                <div className="text-sm text-fg-secondary hidden sm:block">
                  Showing <span className="font-medium">{data.meta.from ?? 0}</span> to{' '}
                  <span className="font-medium">{data.meta.to ?? 0}</span> of{' '}
                  <span className="font-medium">{data.meta.total}</span> results
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => handlePageChange(data.meta.current_page - 1)}
                    disabled={data.meta.current_page <= 1}
                    className="btn btn-outline btn-sm"
                  >
                    Previous
                  </button>
                  <span className="text-sm text-fg-muted">
                    Page {data.meta.current_page} of {data.meta.last_page}
                  </span>
                  <button
                    type="button"
                    onClick={() => handlePageChange(data.meta.current_page + 1)}
                    disabled={data.meta.current_page >= data.meta.last_page}
                    className="btn btn-outline btn-sm"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
