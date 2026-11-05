import { useQuery } from '@tanstack/react-query'
import { useApiClient } from '../../shared/api/apiClientContext'
import { AgentWorkloadTable } from './AgentWorkloadTable'
import { fetchDashboard } from './api'
import { CustomerSummary } from './CustomerSummary'
import { PriorityBar } from './PriorityBar'
import { StatTile } from './StatTile'
import { isStaffDashboard, type DashboardResponse } from './types'

export function DashboardPage() {
  const client = useApiClient()

  const { data, isPending, isError, isFetching, refetch } = useQuery<DashboardResponse>({
    queryKey: ['dashboard'],
    queryFn: () => fetchDashboard(client),
    staleTime: 60 * 1000,
    retry: false,
  })

  if (isPending) {
    return (
      <div className="py-8 text-center text-sm text-fg-muted" role="status">
        Loading dashboard…
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="rounded-lg border border-danger-border bg-danger-subtle p-4 text-sm text-danger-fg">
        <p>Could not load the dashboard.</p>
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={isFetching}
          className="btn btn-danger btn-sm mt-3"
        >
          {isFetching ? 'Retrying…' : 'Retry'}
        </button>
      </div>
    )
  }

  const localTime = new Date(data.generated_at).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })

  const isStaff = isStaffDashboard(data)

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-semibold">Dashboard</h1>
        <div className="flex items-center gap-3 text-sm text-fg-muted">
          <span>Updated {localTime}</span>
          <button
            type="button"
            onClick={() => void refetch()}
            disabled={isFetching}
            className="btn btn-outline btn-xs"
          >
            {isFetching ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      </div>

      {isStaff ? (
        <>
          <section aria-labelledby="team-heading" className="space-y-3">
            <h2 id="team-heading" className="text-lg font-semibold">
              Team overview
            </h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
              <StatTile label="Open" value={data.team.open} to="/tickets?view=active" />
              <StatTile label="Unassigned" value={data.team.unassigned} to="/tickets?assigned=unassigned" />
              <StatTile
                label="Critical"
                value={data.team.critical}
                to="/tickets?priority=critical"
                tone={data.team.critical > 0 ? 'attention' : 'neutral'}
              />
              <StatTile
                label="SLA breached"
                value={data.team.sla_breached}
                to="/tickets?sla_status=breached"
                tone={data.team.sla_breached > 0 ? 'danger' : 'neutral'}
              />
              <StatTile
                label="SLA approaching"
                value={data.team.sla_approaching}
                to="/tickets?sla_status=approaching"
                tone={data.team.sla_approaching > 0 ? 'attention' : 'neutral'}
              />
              <StatTile label="Resolved today" value={data.team.resolved_today} to="/tickets?view=closed" />
              <StatTile label="Resolved (7d)" value={data.team.resolved_last_7_days} to="/tickets?view=closed" />
            </div>
          </section>

          <section aria-labelledby="queue-heading" className="space-y-3">
            <h2 id="queue-heading" className="text-lg font-semibold">
              My queue
            </h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <StatTile label="My open" value={data.my_queue.open} to="/tickets?assigned=me" />
              <StatTile
                label="SLA approaching"
                value={data.my_queue.sla_approaching}
                to="/tickets?assigned=me&sla_status=approaching"
                tone={data.my_queue.sla_approaching > 0 ? 'attention' : 'neutral'}
              />
              <StatTile
                label="SLA breached"
                value={data.my_queue.sla_breached}
                to="/tickets?assigned=me&sla_status=breached"
                tone={data.my_queue.sla_breached > 0 ? 'danger' : 'neutral'}
              />
            </div>
          </section>

          <PriorityBar priorities={data.by_priority} />

          {data.by_agent ? <AgentWorkloadTable agents={data.by_agent} /> : null}
        </>
      ) : (
        <CustomerSummary data={data} />
      )}
    </div>
  )
}
