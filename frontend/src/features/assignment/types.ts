import type { Ticket } from '../tickets/types'

export type AgentCategory = {
  id: number
  name: string
}

export type AgentCandidate = {
  id: number
  name: string
  is_available: boolean
  max_open_tickets: number | null
  categories: AgentCategory[]
  active_ticket_count: number
}

export type AssignTicketPayload = {
  agent_id: number | null
  lock_version: number
}

export type ConflictResponseData = {
  message?: string
  ticket?: Ticket
}

