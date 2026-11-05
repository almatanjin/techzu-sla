import { useHealth } from './useHealth'

export function BackendStatus() {
  const { data, isPending, isFetching, refetch } = useHealth()

  if (isPending) {
    return (
      <StatusCard tone="neutral">
        <p role="status">Checking backend…</p>
      </StatusCard>
    )
  }

  if (data?.status === 'ok') {
    return (
      <StatusCard tone="success">
        <p>Connected</p>
      </StatusCard>
    )
  }

  return (
    <StatusCard tone="danger">
      <p>Unavailable</p>
      <button
        type="button"
        onClick={() => void refetch()}
        disabled={isFetching}
        className="btn btn-danger btn-sm"
      >
        {isFetching ? 'Retrying…' : 'Retry'}
      </button>
    </StatusCard>
  )
}

type Tone = 'neutral' | 'success' | 'danger'

const toneClasses: Record<Tone, string> = {
  neutral: 'border-border bg-surface text-fg-secondary',
  success: 'alert-success',
  danger: 'alert-danger',
}

const dotClasses: Record<Tone, string> = {
  neutral: 'bg-neutral animate-pulse',
  success: 'bg-success',
  danger: 'bg-danger',
}

function StatusCard({ tone, children }: { tone: Tone; children: React.ReactNode }) {
  return (
    <div className={`mt-6 flex flex-wrap items-center gap-3 rounded-lg border p-4 ${toneClasses[tone]}`}>
      <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${dotClasses[tone]}`} />
      <div className="flex flex-1 flex-wrap items-center justify-between gap-3 text-sm font-medium">
        {children}
      </div>
    </div>
  )
}
