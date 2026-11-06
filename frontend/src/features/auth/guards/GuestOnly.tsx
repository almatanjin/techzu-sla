import { Navigate, Outlet, useLocation, type Location } from 'react-router'
import { useAuth } from '../useAuth'

export function GuestOnly() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="flex min-h-64 items-center justify-center text-fg-subtle">
        Loading...
      </div>
    )
  }

  if (status === 'authenticated') {
    const from = (location.state as { from?: Location } | null)?.from
    return <Navigate to={from ? { pathname: from.pathname, search: from.search, hash: from.hash } : '/'} replace />
  }

  return <Outlet />
}
