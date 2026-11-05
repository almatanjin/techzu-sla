import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { Pagination } from '../../shared/components/Pagination'
import { useAuth } from '../auth/useAuth'
import { useActiveCategories } from '../categories/useActiveCategories'
import { PriorityBadge } from './PriorityBadge'
import { StatusBadge } from './StatusBadge'
import { listTickets } from './api'
import type { PaginatedTicketsResponse, TicketPriority, TicketStatus, TicketView } from './types'

export function TicketsListPage() {
  const { user } = useAuth()
  const apiClient = useApiClient()
  const isStaff = user?.role === 'admin' || user?.role === 'agent'
  const { data: categories = [] } = useActiveCategories()

  const [searchParams, setSearchParams] = useSearchParams()

  const rawView = searchParams.get('view')
  const currentView: TicketView = rawView === 'closed' ? 'closed' : 'active'

  const searchFilter = searchParams.get('search') || ''
  const statusFilter = (searchParams.get('status') as TicketStatus | '') || ''
  const priorityFilter = (searchParams.get('priority') as TicketPriority | '') || ''
  const categoryFilter = searchParams.get('category_id') || ''
  const assignedFilter = searchParams.get('assigned') || ''
  const currentPage = Number(searchParams.get('page')) || 1

  const [searchInput, setSearchInput] = useState(searchFilter)

  const { data, isLoading, isError, refetch } = useQuery<PaginatedTicketsResponse>({
    queryKey: [
      'tickets',
      {
        view: currentView,
        status: statusFilter,
        priority: priorityFilter,
        categoryId: categoryFilter,
        search: searchFilter,
        assigned: assignedFilter,
        page: currentPage,
      },
    ],
    queryFn: () =>
      listTickets(apiClient, {
        view: currentView,
        status: statusFilter || undefined,
        priority: priorityFilter || undefined,
        categoryId: categoryFilter || undefined,
        search: searchFilter || undefined,
        assigned: assignedFilter || undefined,
        page: currentPage,
      }),
  })

  const updateFilter = (key: string, value: string | number) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) {
        next.set(key, String(value))
      } else {
        next.delete(key)
      }

      // Sync tabs if status is selected
      if (key === 'status') {
        if (value === 'resolved' || value === 'closed') {
          next.set('view', 'closed')
        } else if (
          value === 'open' ||
          value === 'in_progress' ||
          value === 'waiting_for_customer'
        ) {
          next.set('view', 'active')
        }
      }

      next.delete('page')
      return next
    })
  }

  const clearFilters = () => {
    setSearchParams((prev) => {
      const next = new URLSearchParams()
      const v = prev.get('view')
      if (v) next.set('view', v)
      return next
    })
    setSearchInput('')
  }

  const hasActiveFilters = Boolean(
    searchFilter || statusFilter || priorityFilter || categoryFilter || assignedFilter
  )

  const handleTabChange = (view: TicketView) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('view', view)
      const st = next.get('status')
      if (view === 'active' && (st === 'resolved' || st === 'closed')) {
        next.delete('status')
      } else if (
        view === 'closed' &&
        (st === 'open' || st === 'in_progress' || st === 'waiting_for_customer')
      ) {
        next.delete('status')
      }
      next.delete('page')
      return next
    })
  }

  const handlePageChange = (newPage: number) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (newPage > 1) {
        next.set('page', String(newPage))
      } else {
        next.delete('page')
      }
      return next
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-fg">Tickets</h1>
          <p className="mt-1 text-sm text-fg-muted">
            Track and manage customer support requests.
          </p>
        </div>
        <Link
          to="/tickets/new"
          className="btn btn-primary"
        >
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
      <div className="card p-4 space-y-3">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
          {/* Search */}
          <div>
            <label htmlFor="search-tickets" className="block text-xs font-medium text-fg-secondary">
              Search
            </label>
            <input
              id="search-tickets"
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  updateFilter('search', searchInput)
                }
              }}
              onBlur={() => {
                if (searchInput !== searchFilter) {
                  updateFilter('search', searchInput)
                }
              }}
              placeholder="Subject or ticket #..."
              className="mt-1 input input-sm"
            />
          </div>

          {/* Status */}
          <div>
            <label htmlFor="filter-status" className="block text-xs font-medium text-fg-secondary">
              Status
            </label>
            <select
              id="filter-status"
              value={statusFilter}
              onChange={(e) => updateFilter('status', e.target.value)}
              className="mt-1 input input-sm"
            >
              <option value="">All Statuses</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting_for_customer">Waiting for Customer</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>

          {/* Priority */}
          <div>
            <label htmlFor="filter-priority" className="block text-xs font-medium text-fg-secondary">
              Priority
            </label>
            <select
              id="filter-priority"
              value={priorityFilter}
              onChange={(e) => updateFilter('priority', e.target.value)}
              className="mt-1 input input-sm"
            >
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>

          {/* Category */}
          <div>
            <label htmlFor="filter-category" className="block text-xs font-medium text-fg-secondary">
              Category
            </label>
            <select
              id="filter-category"
              value={categoryFilter}
              onChange={(e) => updateFilter('category_id', e.target.value)}
              className="mt-1 input input-sm"
            >
              <option value="">All Categories</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          {/* Assignment (staff only) */}
          {isStaff && (
            <div>
              <label htmlFor="filter-assigned" className="block text-xs font-medium text-fg-secondary">
                Assignment
              </label>
              <select
                id="filter-assigned"
                value={assignedFilter}
                onChange={(e) => updateFilter('assigned', e.target.value)}
                className="mt-1 input input-sm"
              >
                <option value="">All Tickets</option>
                <option value="me">Assigned to Me</option>
                <option value="unassigned">Unassigned</option>
              </select>
            </div>
          )}
        </div>

        {hasActiveFilters && (
          <div className="flex items-center justify-between pt-2 border-t border-border-subtle text-xs">
            <span className="text-fg-subtle">Filters applied</span>
            <button
              type="button"
              onClick={clearFilters}
              className="link font-medium"
            >
              Reset Filters
            </button>
          </div>
        )}
      </div>

      {/* Content states */}
      {isLoading && (
        <div className="card p-12 text-center text-fg-subtle">
          Loading tickets...
        </div>
      )}

      {isError && (
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

      {!isLoading && !isError && data && (
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
                      <th className="px-6 py-3 font-semibold">Category</th>
                      <th className="px-6 py-3 font-semibold">Customer</th>
                      <th className="px-6 py-3 font-semibold">Assigned</th>
                      <th className="px-6 py-3 font-semibold">Created</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border bg-surface text-fg">
                    {data.data.map((ticket) => (
                      <tr key={ticket.ticket_number} className="hover:bg-surface-muted">
                        <td className="px-6 py-4 font-mono font-medium">
                          <Link
                            to={`/tickets/${ticket.ticket_number}`}
                            className="link"
                          >
                            {ticket.ticket_number}
                          </Link>
                        </td>
                        <td className="px-6 py-4 font-medium text-fg max-w-xs truncate">
                          <Link
                            to={`/tickets/${ticket.ticket_number}`}
                            className="hover:text-accent hover:underline"
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
                  </div>
                ))}
              </div>

              <Pagination meta={data.meta} onPageChange={handlePageChange} />
            </>
          )}
        </div>
      )}
    </div>
  )
}
