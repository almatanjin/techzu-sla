export type SlaStatus = 'within' | 'approaching' | 'breached' | 'met' | 'missed'

export type TicketSla = {
  status: SlaStatus
  started_at: string
  warning_at: string
  due_at: string
  policy_minutes: number
}

export type SlaPolicy = {
  id: number
  priority: 'low' | 'medium' | 'high' | 'critical'
  resolution_minutes: number
  warning_threshold_percent: number
}

export type BusinessHourDay = {
  id?: number
  day_of_week: number
  is_working_day: boolean
  opens_at: string | null
  closes_at: string | null
}

export type BusinessHoursResponse = {
  timezone: string
  days: BusinessHourDay[]
}

export type Holiday = {
  id: number
  date: string
  name: string
}

