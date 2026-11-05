import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import { priorityLabels } from './labels'
import type { TicketPriority } from './types'

const priorityVariants: Record<TicketPriority, BadgeVariant> = {
  low: 'neutral',
  medium: 'info',
  high: 'attention',
  critical: 'danger',
}

type Props = {
  priority: TicketPriority
  className?: string
}

export function PriorityBadge({ priority, className }: Props) {
  return (
    <Badge variant={priorityVariants[priority]} className={className}>
      {priorityLabels[priority] ?? priority}
    </Badge>
  )
}

