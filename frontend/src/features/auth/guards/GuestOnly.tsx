import { Navigate, Outlet } from 'react-router'
import { useAuth } from '../useAuth'

export function GuestOnly() {
  const { status } = useAuth()

  if (status === 'loading') {
    return (
      <div className="flex min-h-64 items-center justify-center text-fg-subtle">
        Loading...
      </div>
    )
  }

  if (status === 'authenticated') {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
