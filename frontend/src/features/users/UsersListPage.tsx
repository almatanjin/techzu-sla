import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { Badge } from '../../shared/components/Badge'
import { Pagination } from '../../shared/components/Pagination'
import type { UserRole } from '../auth/types'
import { listUsers } from './api'
import type { PaginatedUsersResponse } from './types'

export function UsersListPage() {
  const apiClient = useApiClient()
  const [searchParams, setSearchParams] = useSearchParams()

  const roleParam = (searchParams.get('role') as UserRole) || undefined
  const statusParam = (searchParams.get('status') as 'active' | 'inactive') || undefined
  const searchParam = searchParams.get('search') ?? ''
  const pageParam = Number(searchParams.get('page')) || 1

  const [searchInput, setSearchInput] = useState(searchParam)
  const [prevSearchParam, setPrevSearchParam] = useState(searchParam)

  if (prevSearchParam !== searchParam) {
    setPrevSearchParam(searchParam)
    setSearchInput(searchParam)
  }

  useEffect(() => {
    const handler = setTimeout(() => {
      if (searchInput !== searchParam) {
        setSearchParams((prev) => {
          const next = new URLSearchParams(prev)
          if (searchInput.trim()) {
            next.set('search', searchInput.trim())
          } else {
            next.delete('search')
          }
          next.delete('page')
          return next
        })
      }
    }, 300)

    return () => clearTimeout(handler)
  }, [searchInput, searchParam, setSearchParams])

  const { data, isLoading, isError, refetch } = useQuery<PaginatedUsersResponse>({
    queryKey: ['admin', 'users', { role: roleParam, status: statusParam, search: searchParam, page: pageParam }],
    queryFn: () =>
      listUsers(apiClient, {
        role: roleParam,
        status: statusParam,
        search: searchParam || undefined,
        page: pageParam,
      }),
  })

  const handleRoleChange = (newRole: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (newRole) {
        next.set('role', newRole)
      } else {
        next.delete('role')
      }
      next.delete('page')
      return next
    })
  }

  const handleStatusChange = (newStatus: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (newStatus) {
        next.set('status', newStatus)
      } else {
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
          <h1 className="text-2xl font-bold tracking-tight text-fg">Users</h1>
          <p className="mt-1 text-sm text-fg-muted">
            Manage system administrators, support agents, and customer accounts.
          </p>
        </div>
        <Link
          to="/admin/users/new"
          className="btn btn-primary"
        >
          New user
        </Link>
      </div>

      {/* Filters bar */}
      <div className="card flex flex-wrap items-center gap-4 p-4">
        <div className="flex-1 min-w-56">
          <label htmlFor="search-users" className="sr-only">
            Search users
          </label>
          <input
            id="search-users"
            type="search"
            placeholder="Search by name or email..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="input"
          />
        </div>

        <div className="flex items-center gap-2">
          <label htmlFor="filter-role" className="text-sm font-medium text-fg-secondary">
            Role:
          </label>
          <select
            id="filter-role"
            value={roleParam ?? ''}
            onChange={(e) => handleRoleChange(e.target.value)}
            className="input"
          >
            <option value="">All roles</option>
            <option value="admin">Admin</option>
            <option value="agent">Agent</option>
            <option value="customer">Customer</option>
          </select>
        </div>

        <div className="flex items-center gap-2">
          <label htmlFor="filter-status" className="text-sm font-medium text-fg-secondary">
            Status:
          </label>
          <select
            id="filter-status"
            value={statusParam ?? ''}
            onChange={(e) => handleStatusChange(e.target.value)}
            className="input"
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      {/* Main content states */}
      {isLoading && (
        <div className="card p-12 text-center text-fg-subtle">
          Loading users...
        </div>
      )}

      {isError && (
        <div role="alert" className="alert alert-danger p-6 text-center">
          <p>Failed to load users.</p>
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
            <div className="p-12 text-center text-fg-subtle">No users found.</div>
          ) : (
            <>
              {/* Desktop table */}
              <div className="hidden md:block overflow-x-auto">
                <table className="min-w-full divide-y divide-border text-left text-sm">
                  <thead className="bg-surface-muted text-fg-secondary">
                    <tr>
                      <th className="px-6 py-3 font-semibold">Name</th>
                      <th className="px-6 py-3 font-semibold">Email</th>
                      <th className="px-6 py-3 font-semibold">Role</th>
                      <th className="px-6 py-3 font-semibold">Status</th>
                      <th className="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border bg-surface text-fg">
                    {data.data.map((user) => (
                      <tr key={user.id} className="hover:bg-surface-muted">
                        <td className="px-6 py-4 font-medium">{user.name}</td>
                        <td className="px-6 py-4 text-fg-muted">{user.email}</td>
                        <td className="px-6 py-4">
                          <Badge
                            variant={
                              user.role === 'admin'
                                ? 'highlight'
                                : user.role === 'agent'
                                  ? 'info'
                                  : 'neutral'
                            }
                            className="capitalize"
                          >
                            {user.role}
                          </Badge>
                        </td>
                        <td className="px-6 py-4">
                          <Badge variant={user.is_active ? 'success' : 'danger'}>
                            {user.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                        </td>
                        <td className="px-6 py-4 text-right">
                          <Link
                            to={`/admin/users/${user.id}/edit`}
                            className="link font-medium"
                          >
                            Edit
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Mobile cards view */}
              <div className="divide-y divide-border md:hidden">
                {data.data.map((user) => (
                  <div key={user.id} className="space-y-2 p-4">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-fg">{user.name}</span>
                      <Link
                        to={`/admin/users/${user.id}/edit`}
                        className="link text-sm font-medium"
                      >
                        Edit
                      </Link>
                    </div>
                    <div className="text-sm text-fg-muted">{user.email}</div>
                    <div className="flex items-center gap-2 pt-1">
                      <Badge
                        variant={
                          user.role === 'admin'
                            ? 'highlight'
                            : user.role === 'agent'
                              ? 'info'
                              : 'neutral'
                        }
                        className="capitalize"
                      >
                        {user.role}
                      </Badge>
                      <Badge variant={user.is_active ? 'success' : 'danger'}>
                        {user.is_active ? 'Active' : 'Inactive'}
                      </Badge>
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
