export type PaginationMeta = {
  current_page: number
  from?: number | null
  last_page: number
  per_page: number
  to?: number | null
  total: number
}

export type PaginatedResponse<T> = {
  data: T[]
  links?: {
    first?: string
    last?: string
    prev?: string | null
    next?: string | null
  }
  meta: PaginationMeta
}

