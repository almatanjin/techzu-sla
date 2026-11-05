import { Link, NavLink, Outlet } from 'react-router'
import { useAuth } from '../features/auth/useAuth'
import { Badge } from '../shared/components/Badge'

export function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-dvh bg-canvas text-fg">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3">
          <div className="flex items-center gap-6">
            <Link to="/" className="text-lg font-semibold tracking-tight text-fg">
              Techzu SLA
            </Link>
            <nav className="flex items-center gap-4 text-sm font-medium">
              <NavLink to="/" end className="nav-link">
                Home
              </NavLink>
              <NavLink to="/tickets" className="nav-link">
                Tickets
              </NavLink>
              {user?.role === 'admin' && (
                <>
                  <NavLink to="/admin/categories" className="nav-link">
                    Categories
                  </NavLink>
                  <NavLink to="/admin/users" className="nav-link">
                    Users
                  </NavLink>
                </>
              )}
            </nav>
          </div>

          {user && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-medium text-fg">{user.name}</span>
              <Badge variant="neutral" className="capitalize">
                {user.role}
              </Badge>
              <button
                type="button"
                onClick={() => void logout()}
                className="btn btn-outline btn-xs"
              >
                Log out
              </button>
            </div>
          )}
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
