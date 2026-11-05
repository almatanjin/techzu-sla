import { Link } from 'react-router'
import { StatTile } from './StatTile'
import type { CustomerDashboard } from './types'

type Props = {
  data: CustomerDashboard
}

export function CustomerSummary({ data }: Props) {
  return (
    <div className="space-y-8">
      <section aria-labelledby="summary-heading" className="space-y-3">
        <h2 id="summary-heading" className="text-lg font-semibold">
          Your ticket overview
        </h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <StatTile
            label="Open"
            value={data.summary.open}
            to="/tickets?view=active"
          />
          <StatTile
            label="Waiting for your reply"
            value={data.summary.waiting_for_customer}
            to="/tickets?status=waiting_for_customer"
            tone={data.summary.waiting_for_customer > 0 ? 'attention' : 'neutral'}
          />
          <StatTile
            label="Resolved & closed"
            value={data.summary.closed}
            to="/tickets?view=closed"
          />
        </div>
      </section>

      <section aria-labelledby="recent-heading" className="space-y-3">
        <h2 id="recent-heading" className="text-lg font-semibold">
          Recent tickets
        </h2>

        {data.recent_tickets.length === 0 ? (
          <p className="text-sm text-fg-muted">No tickets yet.</p>
        ) : (
          <div className="overflow-hidden rounded-lg border border-border bg-surface">
            <ul className="divide-y divide-border text-sm">
              {data.recent_tickets.map((t) => (
                <li key={t.ticket_number}>
                  <Link
                    to={`/tickets/${t.ticket_number}`}
                    className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-surface-muted/50"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs font-semibold text-fg-muted">
                          {t.ticket_number}
                        </span>
                        <span className="truncate font-medium text-fg">
                          {t.subject}
                        </span>
                      </div>
                      <div className="mt-1 text-xs text-fg-muted capitalize">
                        Status: {t.status.replace(/_/g, ' ')}
                      </div>
                    </div>
                    <div className="text-xs text-fg-muted">
                      {new Date(t.updated_at).toLocaleDateString()}
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        )}
      </section>
    </div>
  )
}

