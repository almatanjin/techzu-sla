import type { AgentWorkload, DailyActivity, PriorityCount, StaffDashboard, TeamCounts } from './types'

/** 14 days ending on 2026-11-06, every count 0 unless overridden by date. */
export function dailyFixture(overrides: Record<string, Partial<Omit<DailyActivity, 'date'>>> = {}): DailyActivity[] {
  const days: DailyActivity[] = []
  for (let day = 24; day <= 37; day++) {
    const date = day <= 31 ? `2026-10-${day}` : `2026-11-0${day - 31}`
    days.push({ date, created: 0, resolved: 0, sla_met: 0, sla_missed: 0, ...overrides[date] })
  }
  return days
}

export function agentRow(row: Partial<AgentWorkload> & Pick<AgentWorkload, 'id' | 'name'>): AgentWorkload {
  return { is_active: true, open: 0, sla_within: 0, sla_approaching: 0, sla_breached: 0, ...row }
}

type StaffOverrides = {
  team?: Partial<TeamCounts>
  my_queue?: Partial<StaffDashboard['my_queue']>
  by_priority?: Partial<Record<PriorityCount['priority'], number>>
  daily?: DailyActivity[]
  by_agent?: AgentWorkload[]
}

/**
 * A staff dashboard body with every count 0 unless overridden.
 * Like the API, an Admin body (one with `by_agent`) has no `my_queue`.
 */
export function staffDashboard(overrides: StaffOverrides = {}): StaffDashboard {
  const priorities: PriorityCount['priority'][] = ['critical', 'high', 'medium', 'low']

  const body: StaffDashboard = {
    generated_at: '2026-11-06T04:00:00Z',
    team: {
      open: 0,
      unassigned: 0,
      critical: 0,
      sla_within: 0,
      sla_approaching: 0,
      sla_breached: 0,
      resolved_today: 0,
      resolved_last_7_days: 0,
      ...overrides.team,
    },
    my_queue: { open: 0, sla_approaching: 0, sla_breached: 0, ...overrides.my_queue },
    by_priority: priorities.map((priority) => ({ priority, count: overrides.by_priority?.[priority] ?? 0 })),
    daily: overrides.daily ?? dailyFixture(),
  }

  if (overrides.by_agent) {
    body.by_agent = overrides.by_agent
    delete body.my_queue
  }

  return body
}
