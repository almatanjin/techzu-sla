export type TeamCounts = {
  open: number
  unassigned: number
  critical: number
  sla_within: number
  sla_approaching: number
  sla_breached: number
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
  sla_within: number
  sla_approaching: number
  sla_breached: number
}

export type DailyActivity = {
  /** Calendar day in the business timezone, `YYYY-MM-DD`. */
  date: string
  created: number
  resolved: number
  sla_met: number
  sla_missed: number
}

export type StaffDashboard = {
  generated_at: string
  team: TeamCounts
  /** Agents only; Admins see every holder's queue in `by_agent`. */
  my_queue?: MyQueueCounts
  by_priority: PriorityCount[]
  /** The last 14 business-timezone days, oldest first and today last. */
  daily: DailyActivity[]
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

