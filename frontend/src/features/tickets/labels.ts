import type { TicketPriority, TicketStatus } from './types'

export const statusLabels: Record<TicketStatus, string> = {
  open: 'Open',
  in_progress: 'In Progress',
  waiting_for_customer: 'Waiting for Customer',
  resolved: 'Resolved',
  closed: 'Closed',
}

export const priorityLabels: Record<TicketPriority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  critical: 'Critical',
}

