export type TicketListFilters = {
  view?: 'active' | 'closed'
  search?: string
  status?: string
  priority?: string
  categoryId?: number
  assigned?: string
  customerId?: number
  createdFrom?: string
  createdTo?: string
  slaStatus?: string
  sort?: string
  perPage?: number
  page?: number
}

export function filtersFromParams(params: URLSearchParams): TicketListFilters {
  const rawView = params.get('view')
  const view = rawView === 'closed' ? 'closed' : rawView === 'active' ? 'active' : undefined
  const search = params.get('search') || undefined
  const status = params.get('status') || undefined
  const priority = params.get('priority') || undefined
  const categoryId = params.get('category_id') ? Number(params.get('category_id')) : undefined
  const assigned = params.get('assigned') || undefined
  const customerId = params.get('customer_id') ? Number(params.get('customer_id')) : undefined
  const createdFrom = params.get('created_from') || undefined
  const createdTo = params.get('created_to') || undefined
  const slaStatus = params.get('sla_status') || undefined
  const sort = params.get('sort') || '-created_at'
  const perPage = params.get('per_page') ? Number(params.get('per_page')) : 20
  const page = params.get('page') ? Number(params.get('page')) : 1

  return {
    view,
    search,
    status,
    priority,
    categoryId,
    assigned,
    customerId,
    createdFrom,
    createdTo,
    slaStatus,
    sort,
    perPage,
    page,
  }
}

export function paramsFromFilters(filters: TicketListFilters): URLSearchParams {
  const params = new URLSearchParams()

  if (filters.view) {
    params.set('view', filters.view)
  }

  if (filters.search) {
    params.set('search', filters.search)
  }
  if (filters.status) {
    params.set('status', filters.status)
  }
  if (filters.priority) {
    params.set('priority', filters.priority)
  }
  if (filters.categoryId) {
    params.set('category_id', String(filters.categoryId))
  }
  if (filters.assigned) {
    params.set('assigned', filters.assigned)
  }
  if (filters.customerId) {
    params.set('customer_id', String(filters.customerId))
  }
  if (filters.createdFrom) {
    params.set('created_from', filters.createdFrom)
  }
  if (filters.createdTo) {
    params.set('created_to', filters.createdTo)
  }
  if (filters.slaStatus) {
    params.set('sla_status', filters.slaStatus)
  }
  if (filters.sort && filters.sort !== '-created_at') {
    params.set('sort', filters.sort)
  }
  if (filters.perPage && filters.perPage !== 20) {
    params.set('per_page', String(filters.perPage))
  }
  if (filters.page && filters.page > 1) {
    params.set('page', String(filters.page))
  }

  return params
}

export function countActiveFilters(filters: TicketListFilters): number {
  let count = 0
  if (filters.search) count++
  if (filters.status) count++
  if (filters.priority) count++
  if (filters.categoryId) count++
  if (filters.assigned) count++
  if (filters.customerId) count++
  if (filters.createdFrom) count++
  if (filters.createdTo) count++
  if (filters.slaStatus) count++
  if (filters.sort && filters.sort !== '-created_at') count++
  if (filters.perPage && filters.perPage !== 20) count++
  return count
}
