import { Outlet } from 'react-router'
import { NotAuthorizedPage } from '../../../app/NotAuthorizedPage'
import type { UserRole } from '../types'
import { useAuth } from '../useAuth'

type Props = {
  role: UserRole
}

export function RequireRole({ role }: Props) {
  const { user } = useAuth()

  if (user?.role !== role) {
    return <NotAuthorizedPage />
  }

  return <Outlet />
}

