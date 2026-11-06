/** The three SLA states of an active ticket, in chart order, with their theme colors. */
export const SLA_PARTS = [
  { key: 'within', label: 'Within SLA', swatchClassName: 'bg-sla-within', color: 'var(--color-sla-within)' },
  { key: 'approaching', label: 'Approaching', swatchClassName: 'bg-sla-approaching', color: 'var(--color-sla-approaching)' },
  { key: 'breached', label: 'Breached', swatchClassName: 'bg-sla-breached', color: 'var(--color-sla-breached)' },
] as const

export type SlaPartKey = (typeof SLA_PARTS)[number]['key']
