import { priorityLabels, statusLabels } from './labels'
import type { TicketActivity, TicketPriority, TicketStatus } from './types'

export function describeActivity(activity: TicketActivity): string {
  switch (activity.type) {
    case 'created':
      return 'Ticket created'

    case 'imported': {
      const ref = activity.new_values?.external_reference ? String(activity.new_values.external_reference) : ''
      return ref ? `Imported from CSV (reference ${ref})` : 'Imported from CSV'
    }

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

    case 'claimed':
      return 'Claimed the ticket'

    case 'assigned': {
      const getAgentName = (val: unknown): string => {
        if (!val) return ''
        if (typeof val === 'string') return val
        if (typeof val === 'object' && val !== null && 'name' in val) {
          return String((val as { name: unknown }).name)
        }
        return String(val)
      }
      const newName = getAgentName(activity.new_values?.agent)
      if (!activity.actor) {
        return `Assigned automatically to ${newName}`
      }
      if (activity.old_values?.agent) {
        const oldName = getAgentName(activity.old_values?.agent)
        return `Reassigned from ${oldName} to ${newName}`
      }
      return `Assigned to ${newName}`
    }

    case 'unassigned': {
      const getAgentName = (val: unknown): string => {
        if (!val) return ''
        if (typeof val === 'string') return val
        if (typeof val === 'object' && val !== null && 'name' in val) {
          return String((val as { name: unknown }).name)
        }
        return String(val)
      }
      const getAgentId = (val: unknown): number | null => {
        if (typeof val === 'object' && val !== null && 'id' in val) {
          return Number((val as { id: unknown }).id)
        }
        return null
      }
      const oldName = getAgentName(activity.old_values?.agent)
      const oldId = getAgentId(activity.old_values?.agent)
      if (!activity.actor) {
        return oldName ? `Unassigned automatically from ${oldName}` : 'Unassigned automatically'
      }
      if (activity.actor && oldId !== null && activity.actor.id === oldId) {
        return 'Released the ticket'
      }
      return oldName ? `Unassigned from ${oldName}` : 'Unassigned'
    }

    case 'sla_warning':
      return 'SLA deadline approaching'

    case 'escalated':
      return 'Escalated: SLA deadline passed'

    case 'replied': {
      const count = Number(activity.new_values?.attachment_count ?? 0)
      if (count > 0) {
        return `Replied with ${count} ${count === 1 ? 'attachment' : 'attachments'}`
      }
      return 'Replied'
    }

    case 'internal_note_added': {
      const count = Number(activity.new_values?.attachment_count ?? 0)
      if (count > 0) {
        return `Added an internal note with ${count} ${count === 1 ? 'attachment' : 'attachments'}`
      }
      return 'Added an internal note'
    }

    default:
      return activity.type
  }
}

