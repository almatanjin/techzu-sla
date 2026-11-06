import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { DailyActivity } from '../types'
import { ChartCard } from './ChartCard'
import { ChartTooltip } from './ChartTooltip'
import { axisTick, formatDayLong, formatDayShort } from './format'

type TooltipProps = {
  active?: boolean
  payload?: ReadonlyArray<{ payload?: DailyActivity }>
}

export function CreatedResolvedTooltip({ active, payload }: TooltipProps) {
  const day = payload?.[0]?.payload
  if (!active || !day) return null

  return (
    <ChartTooltip
      title={formatDayLong(day.date)}
      lines={[
        { key: 'created', text: `Created: ${day.created}`, swatchClassName: 'bg-chart-created' },
        { key: 'resolved', text: `Resolved: ${day.resolved}`, swatchClassName: 'bg-chart-resolved' },
      ]}
    />
  )
}

type Props = {
  daily: DailyActivity[]
}

export function CreatedResolvedChart({ daily }: Props) {
  const isEmpty = daily.every((day) => day.created === 0 && day.resolved === 0)

  return (
    <ChartCard
      title="Created vs resolved"
      isEmpty={isEmpty}
      emptyText="No tickets created or resolved in the last 14 days."
      legend={[
        { key: 'created', label: 'Created', swatchClassName: 'bg-chart-created' },
        { key: 'resolved', label: 'Resolved', swatchClassName: 'bg-chart-resolved' },
      ]}
      table={{
        columns: ['Date', 'Created', 'Resolved'],
        rows: daily.map((day) => [formatDayLong(day.date), day.created, day.resolved]),
      }}
    >
      <div className="h-60 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={daily} margin={{ top: 8, right: 12, bottom: 0, left: -12 }} accessibilityLayer>
            <CartesianGrid vertical={false} stroke="var(--color-border)" />
            <XAxis
              dataKey="date"
              tickFormatter={formatDayShort}
              interval="equidistantPreserveEnd"
              minTickGap={16}
              padding={{ left: 20, right: 20 }}
              tick={axisTick}
              tickLine={false}
              axisLine={{ stroke: 'var(--color-border)' }}
            />
            <YAxis allowDecimals={false} width={40} tick={axisTick} tickLine={false} axisLine={false} />
            <Tooltip
              cursor={{ stroke: 'var(--color-border-strong)' }}
              content={(props) => <CreatedResolvedTooltip active={props.active} payload={props.payload} />}
            />
            <Line
              type="linear"
              dataKey="created"
              name="Created"
              stroke="var(--color-chart-created)"
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--color-surface)' }}
              isAnimationActive={false}
            />
            <Line
              type="linear"
              dataKey="resolved"
              name="Resolved"
              stroke="var(--color-chart-resolved)"
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--color-surface)' }}
              isAnimationActive={false}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </ChartCard>
  )
}
