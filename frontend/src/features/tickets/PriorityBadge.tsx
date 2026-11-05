import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import { priorityLabels } from './labels'
import type { TicketPriority } from './types'

const priorityVariants: Record<TicketPriority, BadgeVariant> = {
  low: 'slate',
  medium: 'blue',
  high: 'orange',
  critical: 'rose',
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

