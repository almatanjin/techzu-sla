import type { PaginationMeta } from '../../shared/api/types'
import type { User, UserRole } from '../auth/types'

export type { PaginationMeta }

export type UserFilters = {
  role?: UserRole
  status?: 'active' | 'inactive'
  search?: string
  page?: number
}

export type PaginatedUsersResponse = {
  data: User[]
  links: {
    first?: string
    last?: string
    prev?: string | null
    next?: string | null
  }
  meta: PaginationMeta
}

export type CreateUserPayload = {
  name: string
  email: string
  role: UserRole
  password: string
  is_active?: boolean
}

export type UpdateUserPayload = {
  name?: string
  email?: string
  role?: UserRole
  password?: string
  is_active?: boolean
  is_available?: boolean
  max_open_tickets?: number | null
  category_ids?: number[]
}

