import type { PaginationMeta } from '../../shared/api/types'

export type TicketStatus =
  | 'open'
  | 'in_progress'
  | 'waiting_for_customer'
  | 'resolved'
  | 'closed'

export type TicketPriority = 'low' | 'medium' | 'high' | 'critical'

export type TicketView = 'active' | 'closed'

export type TicketCustomer = {
  id: number
  name: string
  email: string
}

export type TicketAgent = {
  id: number
  name: string
}

export type TicketCategory = {
  id: number
  name: string
}

export type TicketPermissions = {
  can_edit_content: boolean
  can_edit_classification: boolean
  allowed_statuses: TicketStatus[]
}

export type TicketListItem = {
  ticket_number: string
  subject: string
  priority: TicketPriority
  status: TicketStatus
  customer: TicketCustomer
  assigned_agent: TicketAgent | null
  category: TicketCategory
  created_at: string
}

export type Ticket = {
  ticket_number: string
  subject: string
  description: string
  priority: TicketPriority
  status: TicketStatus
  customer: TicketCustomer
  created_by: { id: number; name: string }
  assigned_agent: TicketAgent | null
  category: TicketCategory
  resolved_at: string | null
  closed_at: string | null
  lock_version: number
  created_at: string
  updated_at: string
  permissions: TicketPermissions
}

export type PaginatedTicketsResponse = {
  data: TicketListItem[]
  links?: {
    first?: string
    last?: string
    prev?: string | null
    next?: string | null
  }
  meta: PaginationMeta
}

export type CreateTicketPayload = {
  subject: string
  description: string
  category_id: number
  priority?: TicketPriority
  customer_id?: number
}

export type UpdateTicketPayload = {
  lock_version: number
  subject?: string
  description?: string
  category_id?: number
  priority?: TicketPriority
}

export type ChangeStatusPayload = {
  status: TicketStatus
  lock_version: number
}

export type TicketActivityActor = {
  id: number
  name: string
  email?: string
}

export type TicketActivity = {
  id: number
  ticket_id: number
  actor: TicketActivityActor | null
  type: string
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  created_at: string
}

