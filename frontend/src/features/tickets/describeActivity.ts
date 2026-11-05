import { priorityLabels, statusLabels } from './labels'
import type { TicketActivity, TicketPriority, TicketStatus } from './types'

export function describeActivity(activity: TicketActivity): string {
  switch (activity.type) {
    case 'created':
      return 'Ticket created'

    case 'status_changed': {
      const fromKey = String(activity.old_values?.status ?? '') as TicketStatus
      const toKey = String(activity.new_values?.status ?? '') as TicketStatus
      const from = statusLabels[fromKey] ?? fromKey
      const to = statusLabels[toKey] ?? toKey
      return `Status changed from ${from} to ${to}`
    }

    case 'priority_changed': {
      const fromKey = String(activity.old_values?.priority ?? '') as TicketPriority
      const toKey = String(activity.new_values?.priority ?? '') as TicketPriority
      const from = priorityLabels[fromKey] ?? fromKey
      const to = priorityLabels[toKey] ?? toKey
      return `Priority changed from ${from} to ${to}`
    }

    case 'category_changed': {
      const getCategoryName = (val: unknown): string => {
        if (!val) return ''
        if (typeof val === 'string') return val
        if (typeof val === 'object' && val !== null && 'name' in val) {
          return String((val as { name: unknown }).name)
        }
        return String(val)
      }
      const from = getCategoryName(activity.old_values?.category)
      const to = getCategoryName(activity.new_values?.category)
      return `Category changed from ${from} to ${to}`
    }

    case 'subject_changed': {
      const from = String(activity.old_values?.subject ?? '')
      const to = String(activity.new_values?.subject ?? '')
      return `Subject changed from "${from}" to "${to}"`
    }

    case 'description_changed':
      return 'Description updated'

    default:
      return activity.type
  }
}

