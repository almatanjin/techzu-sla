import type { ReactNode } from 'react'

export type BadgeVariant =
  | 'slate'
  | 'gray'
  | 'blue'
  | 'emerald'
  | 'green'
  | 'rose'
  | 'red'
  | 'purple'
  | 'amber'
  | 'yellow'
  | 'orange'

export type BadgeProps = {
  children: ReactNode
  variant?: BadgeVariant
  className?: string
}

const variantStyles: Record<BadgeVariant, string> = {
  slate: 'bg-slate-100 text-slate-800',
  gray: 'bg-slate-100 text-slate-800',
  blue: 'bg-blue-100 text-blue-800',
  emerald: 'bg-emerald-100 text-emerald-800',
  green: 'bg-emerald-100 text-emerald-800',
  rose: 'bg-rose-100 text-rose-800',
  red: 'bg-rose-100 text-rose-800',
  purple: 'bg-purple-100 text-purple-800',
  amber: 'bg-amber-100 text-amber-800',
  yellow: 'bg-amber-100 text-amber-800',
  orange: 'bg-orange-100 text-orange-800',
}

export function Badge({ children, variant = 'slate', className = '' }: BadgeProps) {
  const styles = variantStyles[variant] ?? variantStyles.slate
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles} ${className}`.trim()}
    >
      {children}
    </span>
  )
}

