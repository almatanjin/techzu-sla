import type { AxiosInstance } from 'axios'
import type {
  ChangeStatusPayload,
  CreateTicketPayload,
  PaginatedTicketsResponse,
  Ticket,
  TicketActivity,
  TicketCustomer,
  TicketView,
  UpdateTicketPayload,
} from './types'

function unwrapItem<T>(data: T | { data: T }): T {
  if (data && typeof data === 'object' && 'data' in data && !Array.isArray(data)) {
    return (data as { data: T }).data
  }
  return data as T
}

function unwrapArray<T>(data: T[] | { data: T[] }): T[] {
  if (Array.isArray(data)) {
    return data
  }
  if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as { data: T[] }).data)) {
    return (data as { data: T[] }).data
  }
  return []
}

export type ListTicketsOptions = {
  view?: TicketView | 'all'
  status?: string
  priority?: string
  categoryId?: number | string
  search?: string
  assigned?: string
  customer_id?: number | string
  created_from?: string
  created_to?: string
  sla_status?: string
  sort?: string
  per_page?: number
  page?: number
}

export async function listTickets(
  client: AxiosInstance,
  options: ListTicketsOptions = {},
): Promise<PaginatedTicketsResponse> {
  const params = new URLSearchParams()
  if (options.view) {
    params.set('view', options.view)
  }
  if (options.status) {
    params.set('status', options.status)
  }
  if (options.priority) {
    params.set('priority', options.priority)
  }
  if (options.categoryId) {
    params.set('category_id', String(options.categoryId))
  }
  if (options.search) {
    params.set('search', options.search)
  }
  if (options.assigned) {
    params.set('assigned', options.assigned)
  }
  if (options.customer_id) {
    params.set('customer_id', String(options.customer_id))
  }
  if (options.created_from) {
    params.set('created_from', options.created_from)
  }
  if (options.created_to) {
    params.set('created_to', options.created_to)
  }
  if (options.sla_status) {
    params.set('sla_status', options.sla_status)
  }
  if (options.sort) {
    params.set('sort', options.sort)
  }
  if (options.per_page) {
    params.set('per_page', String(options.per_page))
  }
  if (options.page && options.page > 1) {
    params.set('page', String(options.page))
  }

  const response = await client.get<PaginatedTicketsResponse>('tickets', { params })
  return response.data
}

export async function getCustomer(
  client: AxiosInstance,
  id: number | string,
): Promise<TicketCustomer> {
  const response = await client.get<TicketCustomer | { data: TicketCustomer }>(`customers/${id}`)
  return unwrapItem(response.data)
}

export async function getTicket(client: AxiosInstance, ticketNumber: string): Promise<Ticket> {
  const response = await client.get<Ticket | { data: Ticket }>(`tickets/${ticketNumber}`)
  return unwrapItem(response.data)
}

export async function createTicket(
  client: AxiosInstance,
  payload: CreateTicketPayload,
): Promise<Ticket> {
  if (payload.attachments && payload.attachments.length > 0) {
    const formData = new FormData()
    formData.append('subject', payload.subject)
    formData.append('description', payload.description)
    formData.append('category_id', String(payload.category_id))
    if (payload.priority) {
      formData.append('priority', payload.priority)
    }
    if (payload.customer_id !== undefined) {
      formData.append('customer_id', String(payload.customer_id))
    }
    payload.attachments.forEach((file) => {
      formData.append('attachments[]', file, file.name)
    })
    const response = await client.post<Ticket | { data: Ticket }>('tickets', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return unwrapItem(response.data)
  }

  const { attachments: _unused, ...jsonPayload } = payload
  const response = await client.post<Ticket | { data: Ticket }>('tickets', jsonPayload)
  return unwrapItem(response.data)
}

export async function updateTicket(
  client: AxiosInstance,
  ticketNumber: string,
  payload: UpdateTicketPayload,
): Promise<Ticket> {
  const response = await client.patch<Ticket | { data: Ticket }>(`tickets/${ticketNumber}`, payload)
  return unwrapItem(response.data)
}

export async function changeTicketStatus(
  client: AxiosInstance,
  ticketNumber: string,
  payload: ChangeStatusPayload,
): Promise<Ticket> {
  const response = await client.post<Ticket | { data: Ticket }>(`tickets/${ticketNumber}/status`, payload)
  return unwrapItem(response.data)
}

export async function searchCustomers(
  client: AxiosInstance,
  search?: string,
): Promise<TicketCustomer[]> {
  const params = new URLSearchParams()
  if (search) {
    params.set('search', search)
  }
  const response = await client.get<TicketCustomer[] | { data: TicketCustomer[] }>('customers', { params })
  return unwrapArray(response.data)
}

export async function listTicketActivities(
  client: AxiosInstance,
  ticketNumber: string,
): Promise<TicketActivity[]> {
  const response = await client.get<TicketActivity[] | { data: TicketActivity[] }>(`tickets/${ticketNumber}/activities`)
  return unwrapArray(response.data)
}
