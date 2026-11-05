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

export async function listTickets(
  client: AxiosInstance,
  options: { view?: TicketView; page?: number } = {},
): Promise<PaginatedTicketsResponse> {
  const params = new URLSearchParams()
  if (options.view) {
    params.set('view', options.view)
  }
  if (options.page && options.page > 1) {
    params.set('page', String(options.page))
  }

  const response = await client.get<PaginatedTicketsResponse>('tickets', { params })
  return response.data
}

export async function getTicket(client: AxiosInstance, ticketNumber: string): Promise<Ticket> {
  const response = await client.get<Ticket | { data: Ticket }>(`tickets/${ticketNumber}`)
  return unwrapItem(response.data)
}

export async function createTicket(
  client: AxiosInstance,
  payload: CreateTicketPayload,
): Promise<Ticket> {
  const response = await client.post<Ticket | { data: Ticket }>('tickets', payload)
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
