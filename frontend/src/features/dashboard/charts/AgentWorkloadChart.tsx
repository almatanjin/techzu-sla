import { useNavigate } from 'react-router'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { AgentWorkload } from '../types'
import { ChartCard } from './ChartCard'
import { ChartTooltip } from './ChartTooltip'
import { axisTick } from './format'
import { SLA_PARTS, type SlaPartKey } from './slaParts'

const ROW_HEIGHT = 36
const AXIS_HEIGHT = 32
const LABEL_MAX_LENGTH = 18

function agentLabel(agent: Pick<AgentWorkload, 'name' | 'is_active'>): string {
  return agent.is_active ? agent.name : `${agent.name} (inactive)`
}

function shortLabel(label: string): string {
  return label.length > LABEL_MAX_LENGTH ? `${label.slice(0, LABEL_MAX_LENGTH - 1)}…` : label
}

type TooltipProps = {
  active?: boolean
  payload?: ReadonlyArray<{ payload?: AgentWorkload }>
}

export function AgentWorkloadTooltip({ active, payload }: TooltipProps) {
  const agent = payload?.[0]?.payload
  if (!active || !agent) return null

  const counts: Record<SlaPartKey, number> = {
    within: agent.sla_within,
    approaching: agent.sla_approaching,
    breached: agent.sla_breached,
  }

  return (
    <ChartTooltip
      title={agentLabel(agent)}
      lines={SLA_PARTS.map((part) => ({
        key: part.key,
        text: `${part.label}: ${counts[part.key]}`,
        swatchClassName: part.swatchClassName,
      }))}
    />
  )
}

type Props = {
  agents: AgentWorkload[]
}

export function AgentWorkloadChart({ agents }: Props) {
  const navigate = useNavigate()
  const isEmpty = agents.every((agent) => agent.open === 0)
  const rows = agents.map((agent) => ({ ...agent, label: agentLabel(agent) }))
  const lastPart = SLA_PARTS[SLA_PARTS.length - 1].key

  return (
    <ChartCard
      title="Workload by agent"
      isEmpty={isEmpty}
      emptyText="No active tickets are assigned."
      legend={SLA_PARTS.map((part) => ({ key: part.key, label: part.label, swatchClassName: part.swatchClassName }))}
    >
      <div className="w-full" style={{ height: rows.length * ROW_HEIGHT + AXIS_HEIGHT }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart
            data={rows}
            layout="vertical"
            margin={{ top: 0, right: 12, bottom: 0, left: 0 }}
            barCategoryGap={8}
            accessibilityLayer
          >
            <CartesianGrid horizontal={false} stroke="var(--color-border)" />
            <XAxis
              type="number"
              allowDecimals={false}
              tick={axisTick}
              tickLine={false}
              axisLine={{ stroke: 'var(--color-border)' }}
              height={AXIS_HEIGHT}
            />
            <YAxis
              type="category"
              dataKey="label"
              width={128}
              tick={axisTick}
              tickFormatter={shortLabel}
              tickLine={false}
              axisLine={false}
              interval={0}
            />
            <Tooltip
              cursor={{ fill: 'var(--color-surface-muted)' }}
              content={(props) => <AgentWorkloadTooltip active={props.active} payload={props.payload} />}
            />
            {SLA_PARTS.map((part) => (
              <Bar
                key={part.key}
                dataKey={`sla_${part.key}`}
                name={part.label}
                stackId="workload"
                fill={part.color}
                stroke="var(--color-surface)"
                strokeWidth={2}
                radius={part.key === lastPart ? [0, 4, 4, 0] : 0}
                className="cursor-pointer"
                isAnimationActive={false}
                onClick={(bar: { payload?: AgentWorkload }) => {
                  if (bar.payload) void navigate(`/tickets?assigned=${bar.payload.id}&sla_status=${part.key}`)
                }}
              />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartCard>
  )
}
