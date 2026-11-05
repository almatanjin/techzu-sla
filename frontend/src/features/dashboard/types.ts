export type TeamCounts = {
  open: number
  unassigned: number
  critical: number
  sla_breached: number
  sla_approaching: number
  resolved_today: number
  resolved_last_7_days: number
}

export type MyQueueCounts = {
  open: number
  sla_approaching: number
  sla_breached: number
}

export type PriorityCount = {
  priority: 'critical' | 'high' | 'medium' | 'low'
  count: number
}

export type AgentWorkload = {
  id: number
  name: string
  is_active: boolean
  open: number
  sla_approaching: number
  sla_breached: number
}

export type StaffDashboard = {
  generated_at: string
  team: TeamCounts
  my_queue: MyQueueCounts
  by_priority: PriorityCount[]
  by_agent?: AgentWorkload[]
}

export type CustomerSummaryCounts = {
  open: number
  waiting_for_customer: number
  closed: number
}

export type RecentTicket = {
  ticket_number: string
  subject: string
  status: string
  updated_at: string
}

export type CustomerDashboard = {
  generated_at: string
  summary: CustomerSummaryCounts
  recent_tickets: RecentTicket[]
}

export type DashboardResponse = StaffDashboard | CustomerDashboard

export function isStaffDashboard(data: DashboardResponse): data is StaffDashboard {
  return 'team' in data
}

