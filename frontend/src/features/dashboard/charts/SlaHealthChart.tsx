import { useNavigate } from 'react-router'
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import type { TeamCounts } from '../types'
import { ChartCard } from './ChartCard'
import { ChartTooltip } from './ChartTooltip'
import { shareLabel } from './format'
import { SLA_PARTS, type SlaPartKey } from './slaParts'

/** Every part above 0 takes at least this much of the ring (spec: "Small SLA parts stay visible"). */
const MIN_SLICE_DEGREES = 12

type Slice = {
  key: SlaPartKey
  label: string
  count: number
  /** For example `60%`, `<1%` or `>99%`. */
  share: string
}

type TooltipProps = {
  active?: boolean
  payload?: ReadonlyArray<{ payload?: Slice }>
}

export function SlaHealthTooltip({ active, payload }: TooltipProps) {
  const slice = payload?.[0]?.payload
  if (!active || !slice) return null

  const swatch = SLA_PARTS.find((part) => part.key === slice.key)?.swatchClassName

  return (
    <ChartTooltip
      title={slice.label}
      lines={[{ key: 'count', text: `${slice.count} tickets (${slice.share})`, swatchClassName: swatch }]}
    />
  )
}

type Props = {
  team: TeamCounts
}

export function SlaHealthChart({ team }: Props) {
  const navigate = useNavigate()

  const counts: Record<SlaPartKey, number> = {
    within: team.sla_within,
    approaching: team.sla_approaching,
    breached: team.sla_breached,
  }
  const slices: Slice[] = SLA_PARTS.map((part) => ({
    key: part.key,
    label: part.label,
    count: counts[part.key],
    share: shareLabel(counts[part.key], team.open),
  }))

  return (
    <ChartCard
      title="SLA health"
      isEmpty={team.open === 0}
      emptyText="No active tickets."
      legend={slices.map((slice) => ({
        key: slice.key,
        label: slice.label,
        value: `${slice.count} (${slice.share})`,
        swatchClassName: SLA_PARTS.find((part) => part.key === slice.key)!.swatchClassName,
        to: `/tickets?sla_status=${slice.key}`,
      }))}
    >
      <div className="relative h-60 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart accessibilityLayer>
            <Tooltip content={(props) => <SlaHealthTooltip active={props.active} payload={props.payload} />} />
            <Pie
              data={slices.filter((slice) => slice.count > 0)}
              dataKey="count"
              nameKey="label"
              innerRadius="62%"
              outerRadius="92%"
              startAngle={90}
              endAngle={-270}
              // Keep a few within or approaching tickets visible next to thousands of breached ones;
              // the legend and tooltip keep the exact counts.
              minAngle={MIN_SLICE_DEGREES}
              stroke="var(--color-surface)"
              strokeWidth={2}
              isAnimationActive={false}
              onClick={(entry: { payload?: Slice }) => {
                if (entry.payload) void navigate(`/tickets?sla_status=${entry.payload.key}`)
              }}
            >
              {slices
                .filter((slice) => slice.count > 0)
                .map((slice) => (
                  <Cell
                    key={slice.key}
                    fill={SLA_PARTS.find((part) => part.key === slice.key)!.color}
                    className="cursor-pointer"
                  />
                ))}
            </Pie>
          </PieChart>
        </ResponsiveContainer>

        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-3xl font-semibold text-fg tabular-nums">{team.open}</span>
          <span className="text-xs text-fg-muted">active</span>
        </div>
      </div>
    </ChartCard>
  )
}
