import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { listAgents } from '../assignment/api'
import type { AgentCandidate } from '../assignment/types'
import { useActiveCategories } from '../categories/useActiveCategories'
import { CustomerPicker } from './CustomerPicker'
import { countActiveFilters, type TicketListFilters } from './listFilters'

type Props = {
  filters: TicketListFilters
  role: 'admin' | 'agent' | 'customer'
  onChange: (filters: TicketListFilters) => void
  searchError?: string
  onClearSearchError?: () => void
}

export function TicketFilterBar({
  filters,
  role,
  onChange,
  searchError,
  onClearSearchError,
}: Props) {
  const apiClient = useApiClient()
  const isStaff = role === 'admin' || role === 'agent'
  const isAdmin = role === 'admin'

  const { data: categories = [] } = useActiveCategories()

  const { data: agents = [] } = useQuery<AgentCandidate[]>({
    queryKey: ['admin', 'agents'],
    queryFn: () => listAgents(apiClient),
    enabled: isAdmin,
  })

  const [searchInput, setSearchInput] = useState(filters.search ?? '')
  const [prevSearch, setPrevSearch] = useState(filters.search)

  if (filters.search !== prevSearch) {
    setPrevSearch(filters.search)
    setSearchInput(filters.search ?? '')
  }

  const handleSearchSubmit = () => {
    const trimmed = searchInput.trim()
    if (trimmed !== (filters.search ?? '')) {
      onClearSearchError?.()
      onChange({
        ...filters,
        search: trimmed || undefined,
        page: undefined,
      })
    }
  }

  const updateField = <K extends keyof TicketListFilters>(key: K, value: TicketListFilters[K]) => {
    const next: TicketListFilters = {
      ...filters,
      [key]: value || undefined,
      page: undefined,
    }

    if (key === 'status') {
      const st = value as string
      if (st === 'resolved' || st === 'closed') {
        next.view = 'closed'
      } else if (st === 'open' || st === 'in_progress' || st === 'waiting_for_customer') {
        next.view = 'active'
      }
    }

    onChange(next)
  }

  const handleReset = () => {
    onClearSearchError?.()
    setSearchInput('')
    onChange({
      view: filters.view,
    })
  }

  const activeCount = countActiveFilters(filters)

  // If assigned is an agent ID not present in active agents list
  const unknownAssignedId =
    isAdmin &&
    filters.assigned &&
    filters.assigned !== 'me' &&
    filters.assigned !== 'unassigned' &&
    !agents.some((a) => String(a.id) === filters.assigned)
      ? filters.assigned
      : null

  return (
    <div className="card p-4 space-y-4">
      {/* Search box spanning full width */}
      <div>
        <label htmlFor="search-tickets" className="block text-xs font-medium text-fg-secondary">
          Search
        </label>
        <input
          id="search-tickets"
          type="text"
          value={searchInput}
          onChange={(e) => {
            setSearchInput(e.target.value)
            onClearSearchError?.()
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              handleSearchSubmit()
            }
          }}
          onBlur={handleSearchSubmit}
          placeholder="Subject or ticket #..."
          className="mt-1 input input-sm w-full"
        />
        {searchError && (
          <p className="mt-1 text-xs text-danger font-medium">{searchError}</p>
        )}
      </div>

      {/* Filter grid: 1 col on phone, 2 on tablet, 4 on desktop */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {/* Status */}
        <div>
          <label htmlFor="filter-status" className="block text-xs font-medium text-fg-secondary">
            Status
          </label>
          <select
            id="filter-status"
            value={filters.status ?? ''}
            onChange={(e) => updateField('status', e.target.value)}
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
            value={filters.priority ?? ''}
            onChange={(e) => updateField('priority', e.target.value)}
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
            value={filters.categoryId ?? ''}
            onChange={(e) => updateField('categoryId', e.target.value ? Number(e.target.value) : undefined)}
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

        {/* Sort */}
        <div>
          <label htmlFor="filter-sort" className="block text-xs font-medium text-fg-secondary">
            Sort
          </label>
          <select
            id="filter-sort"
            value={filters.sort ?? '-created_at'}
            onChange={(e) => updateField('sort', e.target.value)}
            className="mt-1 input input-sm"
          >
            <option value="-created_at">Newest first</option>
            <option value="created_at">Oldest first</option>
            <option value="-updated_at">Recently updated</option>
            <option value="-priority">Priority: highest first</option>
            <option value="priority">Priority: lowest first</option>
            {isStaff && <option value="sla_due_at">SLA due: soonest first</option>}
          </select>
        </div>

        {/* Created from */}
        <div>
          <label htmlFor="filter-created-from" className="block text-xs font-medium text-fg-secondary">
            Created from
          </label>
          <input
            id="filter-created-from"
            type="date"
            value={filters.createdFrom ?? ''}
            onChange={(e) => updateField('createdFrom', e.target.value)}
            className="mt-1 input input-sm"
          />
        </div>

        {/* Created to */}
        <div>
          <label htmlFor="filter-created-to" className="block text-xs font-medium text-fg-secondary">
            Created to
          </label>
          <input
            id="filter-created-to"
            type="date"
            min={filters.createdFrom ?? undefined}
            value={filters.createdTo ?? ''}
            onChange={(e) => updateField('createdTo', e.target.value)}
            className="mt-1 input input-sm"
          />
        </div>

        {/* Assignment (staff only) */}
        {isStaff && (
          <div>
            <label htmlFor="filter-assigned" className="block text-xs font-medium text-fg-secondary">
              Assignment
            </label>
            <select
              id="filter-assigned"
              value={filters.assigned ?? ''}
              onChange={(e) => updateField('assigned', e.target.value)}
              className="mt-1 input input-sm"
            >
              <option value="">All</option>
              <option value="me">Assigned to me</option>
              <option value="unassigned">Unassigned</option>
              {isAdmin &&
                agents.map((agent) => (
                  <option key={agent.id} value={String(agent.id)}>
                    {agent.name}
                  </option>
                ))}
              {unknownAssignedId && (
                <option value={unknownAssignedId}>Agent #{unknownAssignedId}</option>
              )}
            </select>
          </div>
        )}

        {/* Customer (staff only) */}
        {isStaff && (
          <div>
            <CustomerPicker
              compact
              value={filters.customerId ?? null}
              onChange={(cid) => updateField('customerId', cid ?? undefined)}
            />
          </div>
        )}

        {/* SLA Status (staff only) */}
        {isStaff && (
          <div>
            <label htmlFor="filter-sla-status" className="block text-xs font-medium text-fg-secondary">
              SLA Status
            </label>
            <select
              id="filter-sla-status"
              value={filters.slaStatus ?? ''}
              onChange={(e) => updateField('slaStatus', e.target.value)}
              className="mt-1 input input-sm"
            >
              <option value="">All SLA Statuses</option>
              <option value="within">Within SLA</option>
              <option value="approaching">Approaching</option>
              <option value="breached">Breached</option>
              <option value="met">Met</option>
              <option value="missed">Missed</option>
            </select>
          </div>
        )}
      </div>

      {activeCount > 0 && (
        <div className="flex items-center justify-between pt-2 border-t border-border-subtle text-xs">
          <span className="text-fg-subtle">
            {activeCount} {activeCount === 1 ? 'filter' : 'filters'} applied
          </span>
          <button
            type="button"
            onClick={handleReset}
            className="link font-medium"
          >
            Reset filters
          </button>
        </div>
      )}
    </div>
  )
}
