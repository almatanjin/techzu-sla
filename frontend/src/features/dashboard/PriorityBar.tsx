import { Link } from 'react-router'
import type { PriorityCount } from './types'

const priorityStyles: Record<
  PriorityCount['priority'],
  { label: string; bg: string }
> = {
  critical: { label: 'Critical', bg: 'bg-danger' },
  high: { label: 'High', bg: 'bg-attention' },
  medium: { label: 'Medium', bg: 'bg-info' },
  low: { label: 'Low', bg: 'bg-neutral' },
}

type Props = {
  priorities: PriorityCount[]
}

export function PriorityBar({ priorities }: Props) {
  const total = priorities.reduce((sum, p) => sum + p.count, 0)

  return (
    <section aria-labelledby="priority-heading" className="space-y-3">
      <h2 id="priority-heading" className="text-lg font-semibold">
        Tickets by priority
      </h2>

      {total === 0 ? (
        <p className="text-sm text-fg-muted">No active tickets.</p>
      ) : (
        <div className="space-y-3">
          <div
            role="progressbar"
            aria-label="Active tickets by priority distribution"
            aria-valuenow={total}
            aria-valuemin={0}
            aria-valuemax={total}
            className="flex h-4 w-full overflow-hidden rounded-full bg-surface-muted"
          >
            {priorities.map((p) => {
              if (p.count === 0) return null
              const pct = (p.count / total) * 100
              const info = priorityStyles[p.priority]

              return (
                <div
                  key={p.priority}
                  style={{ width: `${pct}%` }}
                  aria-label={`${info.label}: ${p.count} (${pct.toFixed(0)}%)`}
                  className={`${info.bg} h-full transition-all`}
                />
              )
            })}
          </div>

          <div className="flex flex-wrap items-center gap-4 text-sm">
            {priorities.map((p) => {
              const info = priorityStyles[p.priority]

              return (
                <Link
                  key={p.priority}
                  to={`/tickets?priority=${p.priority}`}
                  className="inline-flex items-center gap-2 font-medium hover:underline"
                >
                  <span
                    aria-hidden="true"
                    className={`size-2.5 rounded-full ${info.bg}`}
                  />
                  <span>
                    {info.label}: {p.count}
                  </span>
                </Link>
              )
            })}
          </div>
        </div>
      )}
    </section>
  )
}

