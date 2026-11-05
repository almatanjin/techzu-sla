import { Outlet } from 'react-router'
import { NotAuthorizedPage } from '../../../app/NotAuthorizedPage'
import type { UserRole } from '../types'
import { useAuth } from '../useAuth'

type Props = {
  role?: UserRole
  roles?: UserRole[]
}

export function RequireRole({ role, roles }: Props) {
  const { user } = useAuth()

  const allowedRoles = roles ?? (role ? [role] : [])
  if (!user || !allowedRoles.includes(user.role)) {
    return <NotAuthorizedPage />
  }

  return <Outlet />
}

