import type { AxiosInstance } from 'axios'
import type { Ticket } from '../tickets/types'
import type { AgentCandidate, AssignTicketPayload } from './types'

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

export async function assignTicket(
  client: AxiosInstance,
  ticketNumber: string,
  payload: AssignTicketPayload,
): Promise<Ticket> {
  const response = await client.put<Ticket | { data: Ticket }>(
    `tickets/${ticketNumber}/assignment`,
    payload,
  )
  return unwrapItem(response.data)
}

export async function claimTicket(
  client: AxiosInstance,
  ticketNumber: string,
): Promise<Ticket> {
  const response = await client.post<Ticket | { data: Ticket }>(
    `tickets/${ticketNumber}/claim`,
  )
  return unwrapItem(response.data)
}

export async function releaseTicket(
  client: AxiosInstance,
  ticketNumber: string,
): Promise<Ticket> {
  const response = await client.post<Ticket | { data: Ticket }>(
    `tickets/${ticketNumber}/release`,
  )
  return unwrapItem(response.data)
}

export async function listAgents(
  client: AxiosInstance,
): Promise<AgentCandidate[]> {
  const response = await client.get<AgentCandidate[] | { data: AgentCandidate[] }>(
    'admin/agents',
  )
  return unwrapArray(response.data)
}

