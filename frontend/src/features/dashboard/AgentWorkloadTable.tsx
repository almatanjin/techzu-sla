import { Link } from 'react-router'
import type { AgentWorkload } from './types'

type Props = {
  agents: AgentWorkload[]
}

export function AgentWorkloadTable({ agents }: Props) {
  return (
    <section aria-labelledby="agents-heading" className="space-y-3">
      <h2 id="agents-heading" className="text-lg font-semibold">
        Tickets by agent
      </h2>

      <div className="overflow-x-auto rounded-lg border border-border bg-surface">
        <table className="w-full min-w-[320px] text-left text-sm">
          <thead className="border-b border-border bg-surface-muted text-xs uppercase text-fg-muted">
            <tr>
              <th scope="col" className="px-4 py-3 font-semibold">
                Agent
              </th>
              <th scope="col" className="px-4 py-3 font-semibold text-right">
                Open
              </th>
              <th scope="col" className="px-4 py-3 font-semibold text-right">
                Approaching
              </th>
              <th scope="col" className="px-4 py-3 font-semibold text-right">
                Breached
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {agents.map((agent) => (
              <tr key={agent.id} className="hover:bg-surface-muted/50">
                <td className="px-4 py-3 font-medium whitespace-nowrap">
                  <Link
                    to={`/tickets?assigned=${agent.id}`}
                    className="hover:underline text-fg font-medium"
                  >
                    {agent.name}
                  </Link>
                  {!agent.is_active && (
                    <span className="ml-2 text-xs font-normal text-fg-muted">
                      (inactive)
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-right tabular-nums">{agent.open}</td>
                <td className="px-4 py-3 text-right tabular-nums text-attention-fg">
                  {agent.sla_approaching}
                </td>
                <td className="px-4 py-3 text-right tabular-nums text-danger-fg">
                  {agent.sla_breached}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
