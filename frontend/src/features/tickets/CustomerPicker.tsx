import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { getCustomer, searchCustomers } from './api'
import type { TicketCustomer } from './types'

type Props = {
  value: number | null
  onChange: (customerId: number | null) => void
  error?: string
  compact?: boolean
}

export function CustomerPicker({ value, onChange, error, compact }: Props) {
  const apiClient = useApiClient()
  const [searchTerm, setSearchTerm] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [selectedCustomer, setSelectedCustomer] = useState<TicketCustomer | null>(null)
  const [isOpen, setIsOpen] = useState(false)

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(searchTerm.trim())
    }, 300)
    return () => clearTimeout(handler)
  }, [searchTerm])

  const { data: initialCustomer } = useQuery<TicketCustomer>({
    queryKey: ['customer', value],
    queryFn: () => getCustomer(apiClient, value!),
    enabled: Boolean(value) && (!selectedCustomer || selectedCustomer.id !== value),
  })

  const currentCustomer =
    value && selectedCustomer?.id === value
      ? selectedCustomer
      : value && initialCustomer?.id === value
        ? initialCustomer
        : null

  const { data: results = [], isLoading } = useQuery<TicketCustomer[]>({
    queryKey: ['customers', { search: debouncedSearch }],
    queryFn: () => searchCustomers(apiClient, debouncedSearch),
    enabled: debouncedSearch.length >= 1,
  })

  const handleSelect = (customer: TicketCustomer) => {
    setSelectedCustomer(customer)
    onChange(customer.id)
    setIsOpen(false)
    setSearchTerm('')
  }

  const handleClear = () => {
    setSelectedCustomer(null)
    onChange(null)
    setSearchTerm('')
  }

  return (
    <div className={compact ? '' : 'space-y-1'}>
      <label
        htmlFor="customer-search"
        className={compact ? 'block text-xs font-medium text-fg-secondary' : 'label'}
      >
        Customer
      </label>

      {currentCustomer && value ? (
        <div
          className={
            compact
              ? 'mt-1 flex items-center justify-between rounded-md border border-border bg-canvas px-2.5 py-1 text-xs'
              : 'flex items-center justify-between rounded-md border border-border-strong bg-canvas px-3 py-2 text-sm'
          }
        >
          <div className="truncate">
            <span className="font-medium text-fg">{currentCustomer.name}</span>
            <span className="ml-1 text-fg-subtle">({currentCustomer.email})</span>
          </div>
          <button
            type="button"
            onClick={handleClear}
            className="text-xs font-semibold link ml-2 shrink-0"
          >
            Clear
          </button>
        </div>
      ) : (
        <div className="relative">
          <input
            id="customer-search"
            type="text"
            placeholder={compact ? 'Search customer...' : 'Search active customer by name or email...'}
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value)
              setIsOpen(true)
            }}
            onFocus={() => setIsOpen(true)}
            className={compact ? 'mt-1 input input-sm' : 'input'}
          />

          {isOpen && debouncedSearch.length >= 1 && (
            <div className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-border bg-surface py-1 shadow-lg">
              {isLoading && (
                <div className="px-4 py-2 text-xs text-fg-subtle">Searching customers...</div>
              )}
              {!isLoading && results.length === 0 && (
                <div className="px-4 py-2 text-xs text-fg-subtle">No customers found.</div>
              )}
              {!isLoading &&
                results.map((c) => (
                  <button
                    key={c.id}
                    type="button"
                    onClick={() => handleSelect(c)}
                    className="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-surface-muted"
                  >
                    <span className="font-medium text-fg">{c.name}</span>
                    <span className="text-xs text-fg-subtle">{c.email}</span>
                  </button>
                ))}
            </div>
          )}
        </div>
      )}

      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  )
}
