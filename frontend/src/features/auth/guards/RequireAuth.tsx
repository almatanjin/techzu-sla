import { Navigate, Outlet, useLocation } from 'react-router'
import { consumeSessionEnded } from '../sessionEndedTracker'
import { useAuth } from '../useAuth'

export function RequireAuth() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="flex min-h-64 items-center justify-center text-fg-subtle">
        Loading session...
      </div>
    )
  }

  if (status === 'guest') {
    const sessionEnded = consumeSessionEnded() || Boolean((location.state as { sessionEnded?: boolean })?.sessionEnded)
    return (
      <Navigate
        to="/login"
        state={{
          from: location,
          sessionEnded,
        }}
        replace
      />
    )
  }

  return <Outlet />
}
