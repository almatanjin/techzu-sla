import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import { statusLabels } from './labels'
import type { TicketStatus } from './types'

const statusVariants: Record<TicketStatus, BadgeVariant> = {
  open: 'blue',
  in_progress: 'amber',
  waiting_for_customer: 'orange',
  resolved: 'emerald',
  closed: 'slate',
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

