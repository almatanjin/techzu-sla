import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import type { SlaStatus } from './types'

export type SlaBadgeProps = {
  status: SlaStatus
  escalated?: boolean
  className?: string
}

const statusConfig: Record<SlaStatus, { label: string; variant: BadgeVariant }> = {
  within: { label: 'Within SLA', variant: 'info' },
  approaching: { label: 'Approaching', variant: 'warning' },
  breached: { label: 'Breached', variant: 'danger' },
  met: { label: 'Met', variant: 'success' },
  missed: { label: 'Missed', variant: 'attention' },
}

export function SlaBadge({ status, escalated = false, className = '' }: SlaBadgeProps) {
  const config = statusConfig[status] ?? { label: status, variant: 'neutral' as BadgeVariant }

  return (
    <span className={`inline-flex flex-wrap items-center gap-1.5 ${className}`.trim()}>
      <Badge variant={config.variant}>{config.label}</Badge>
      {escalated && <Badge variant="danger">Escalated</Badge>}
    </span>
  )
}

