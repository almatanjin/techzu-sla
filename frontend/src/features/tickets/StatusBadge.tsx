import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import { statusLabels } from './labels'
import type { TicketStatus } from './types'

const statusVariants: Record<TicketStatus, BadgeVariant> = {
  open: 'info',
  in_progress: 'warning',
  waiting_for_customer: 'attention',
  resolved: 'success',
  closed: 'neutral',
}

type Props = {
  status: TicketStatus
  className?: string
}

export function StatusBadge({ status, className }: Props) {
  return (
    <Badge variant={statusVariants[status]} className={className}>
      {statusLabels[status] ?? status}
    </Badge>
  )
}

