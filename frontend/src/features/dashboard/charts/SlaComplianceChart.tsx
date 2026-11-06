import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { DailyActivity } from '../types'
import { ChartCard } from './ChartCard'
import { ChartTooltip } from './ChartTooltip'
import { axisTick, formatDayLong, formatDayShort, percentOf } from './format'

type TooltipProps = {
  active?: boolean
  payload?: ReadonlyArray<{ payload?: DailyActivity }>
}

export function SlaComplianceTooltip({ active, payload }: TooltipProps) {
  const day = payload?.[0]?.payload
  if (!active || !day) return null

  const finished = day.sla_met + day.sla_missed

  return (
    <ChartTooltip
      title={formatDayLong(day.date)}
      lines={[
        { key: 'met', text: `Met SLA: ${day.sla_met}`, swatchClassName: 'bg-sla-within' },
        { key: 'missed', text: `Missed SLA: ${day.sla_missed}`, swatchClassName: 'bg-sla-breached' },
        finished === 0
          ? { key: 'share', text: 'No tickets finished' }
          : { key: 'share', text: `${percentOf(day.sla_met, finished)}% met` },
      ]}
    />
  )
}

type Props = {
  daily: DailyActivity[]
}

export function SlaComplianceChart({ daily }: Props) {
  const isEmpty = daily.every((day) => day.sla_met === 0 && day.sla_missed === 0)

  return (
    <ChartCard
      title="Daily SLA compliance"
      isEmpty={isEmpty}
      emptyText="No tickets finished in the last 14 days."
      legend={[
        { key: 'met', label: 'Met SLA', swatchClassName: 'bg-sla-within' },
        { key: 'missed', label: 'Missed SLA', swatchClassName: 'bg-sla-breached' },
      ]}
      table={{
        columns: ['Date', 'Met SLA', 'Missed SLA'],
        rows: daily.map((day) => [formatDayLong(day.date), day.sla_met, day.sla_missed]),
      }}
    >
      <div className="h-60 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={daily} margin={{ top: 8, right: 12, bottom: 0, left: -12 }} accessibilityLayer>
            <CartesianGrid vertical={false} stroke="var(--color-border)" />
            <XAxis
              dataKey="date"
              tickFormatter={formatDayShort}
              interval="equidistantPreserveEnd"
              minTickGap={16}
              tick={axisTick}
              tickLine={false}
              axisLine={{ stroke: 'var(--color-border)' }}
            />
            <YAxis allowDecimals={false} width={40} tick={axisTick} tickLine={false} axisLine={false} />
            <Tooltip
              cursor={{ fill: 'var(--color-surface-muted)' }}
              content={(props) => <SlaComplianceTooltip active={props.active} payload={props.payload} />}
            />
            <Bar
              dataKey="sla_met"
              name="Met SLA"
              stackId="finished"
              fill="var(--color-sla-within)"
              stroke="var(--color-surface)"
              strokeWidth={2}
              maxBarSize={28}
              isAnimationActive={false}
            />
            <Bar
              dataKey="sla_missed"
              name="Missed SLA"
              stackId="finished"
              fill="var(--color-sla-breached)"
              stroke="var(--color-surface)"
              strokeWidth={2}
              radius={[4, 4, 0, 0]}
              maxBarSize={28}
              isAnimationActive={false}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartCard>
  )
}
