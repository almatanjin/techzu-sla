import type { User, UserRole } from '../auth/types'

export type UserFilters = {
  role?: UserRole
  status?: 'active' | 'inactive'
  search?: string
  page?: number
}

export type PaginationMeta = {
  current_page: number
  from?: number | null
  last_page: number
  per_page: number
  to?: number | null
  total: number
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
}

