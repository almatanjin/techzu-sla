import { Link } from 'react-router'

type Tone = 'danger' | 'attention' | 'neutral'

type Props = {
  label: string
  value: number | string
  to: string
  tone?: Tone
}

export function StatTile({ label, value, to, tone = 'neutral' }: Props) {
  const toneClasses = {
    danger: 'border-danger-border bg-danger-subtle text-danger-fg hover:border-danger hover:bg-danger-soft',
    attention: 'border-attention-border bg-attention-subtle text-attention-fg hover:border-attention hover:bg-attention-soft',
    neutral: 'border-border bg-surface text-fg hover:border-border-strong hover:bg-surface-muted',
  }[tone]

  return (
    <Link
      to={to}
      aria-label={`${label}: ${value}`}
      className={`block rounded-lg border p-4 transition-colors ${toneClasses}`}
    >
      <div className="text-xs font-medium tracking-wide uppercase text-fg-muted">{label}</div>
      <div className="mt-1 text-2xl font-semibold">{value}</div>
    </Link>
  )
}

