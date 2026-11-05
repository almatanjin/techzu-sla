import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { searchCustomers } from './api'
import type { TicketCustomer } from './types'

type Props = {
  value: number | null
  onChange: (customerId: number | null) => void
  error?: string
}

export function CustomerPicker({ value, onChange, error }: Props) {
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
    <div className="space-y-1">
      <label htmlFor="customer-search" className="block text-sm font-medium text-slate-700">
        Customer
      </label>

      {selectedCustomer && value ? (
        <div className="flex items-center justify-between rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm">
          <div>
            <span className="font-medium text-slate-900">{selectedCustomer.name}</span>
            <span className="ml-2 text-slate-500">({selectedCustomer.email})</span>
          </div>
          <button
            type="button"
            onClick={handleClear}
            className="text-xs font-semibold text-blue-600 hover:text-blue-500 hover:underline"
          >
            Change
          </button>
        </div>
      ) : (
        <div className="relative">
          <input
            id="customer-search"
            type="text"
            placeholder="Search active customer by name or email..."
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value)
              setIsOpen(true)
            }}
            onFocus={() => setIsOpen(true)}
            className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />

          {isOpen && debouncedSearch.length >= 1 && (
            <div className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
              {isLoading && (
                <div className="px-4 py-2 text-xs text-slate-500">Searching customers...</div>
              )}
              {!isLoading && results.length === 0 && (
                <div className="px-4 py-2 text-xs text-slate-500">No customers found.</div>
              )}
              {!isLoading &&
                results.map((c) => (
                  <button
                    key={c.id}
                    type="button"
                    onClick={() => handleSelect(c)}
                    className="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-slate-50"
                  >
                    <span className="font-medium text-slate-900">{c.name}</span>
                    <span className="text-xs text-slate-500">{c.email}</span>
                  </button>
                ))}
            </div>
          )}
        </div>
      )}

      {error && (
        <p role="alert" className="mt-1.5 text-xs text-rose-600">
          {error}
        </p>
      )}
    </div>
  )
}
