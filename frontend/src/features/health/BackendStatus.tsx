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
        className="rounded-md bg-red-700 px-3 py-1.5 text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-60"
      >
        {isFetching ? 'Retrying…' : 'Retry'}
      </button>
    </StatusCard>
  )
}

type Tone = 'neutral' | 'success' | 'danger'

const toneClasses: Record<Tone, string> = {
  neutral: 'border-slate-200 bg-white text-slate-700',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  danger: 'border-red-200 bg-red-50 text-red-800',
}

const dotClasses: Record<Tone, string> = {
  neutral: 'bg-slate-400 animate-pulse',
  success: 'bg-emerald-500',
  danger: 'bg-red-500',
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
