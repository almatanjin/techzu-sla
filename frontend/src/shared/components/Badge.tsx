import type { ReactNode } from 'react'

export type BadgeVariant =
  | 'neutral'
  | 'info'
  | 'success'
  | 'warning'
  | 'attention'
  | 'danger'
  | 'highlight'

export type BadgeProps = {
  children: ReactNode
  variant?: BadgeVariant
  className?: string
}

const variantStyles: Record<BadgeVariant, string> = {
  neutral: 'bg-neutral-soft text-neutral-fg',
  info: 'bg-info-soft text-info-fg',
  success: 'bg-success-soft text-success-fg',
  warning: 'bg-warning-soft text-warning-fg',
  attention: 'bg-attention-soft text-attention-fg',
  danger: 'bg-danger-soft text-danger-fg',
  highlight: 'bg-highlight-soft text-highlight-fg',
}

export function Badge({ children, variant = 'neutral', className = '' }: BadgeProps) {
  const styles = variantStyles[variant] ?? variantStyles.neutral
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles} ${className}`.trim()}
    >
      {children}
    </span>
  )
}
