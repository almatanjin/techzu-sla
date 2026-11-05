import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import type { SlaStatus } from './types'

export type SlaBadgeProps = {
  status: SlaStatus
  className?: string
}

const statusConfig: Record<SlaStatus, { label: string; variant: BadgeVariant }> = {
  within: { label: 'Within SLA', variant: 'info' },
  approaching: { label: 'Approaching', variant: 'warning' },
  breached: { label: 'Breached', variant: 'danger' },
  met: { label: 'Met', variant: 'success' },
  missed: { label: 'Missed', variant: 'attention' },
}

export function SlaBadge({ status, className = '' }: SlaBadgeProps) {
  const config = statusConfig[status] ?? { label: status, variant: 'neutral' as BadgeVariant }

  return (
    <Badge variant={config.variant} className={className}>
      {config.label}
    </Badge>
  )
}

