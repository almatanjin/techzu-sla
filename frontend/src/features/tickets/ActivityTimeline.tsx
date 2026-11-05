import { useQuery } from '@tanstack/react-query'
import { useApiClient } from '../../shared/api/apiClientContext'
import { listTicketActivities } from './api'
import { describeActivity } from './describeActivity'
import type { TicketActivity } from './types'

type Props = {
  ticketNumber: string
}

export function ActivityTimeline({ ticketNumber }: Props) {
  const apiClient = useApiClient()

  const {
    data: activities,
    isLoading,
    isError,
    refetch,
  } = useQuery<TicketActivity[]>({
    queryKey: ['tickets', ticketNumber, 'activities'],
    queryFn: () => listTicketActivities(apiClient, ticketNumber),
    enabled: Boolean(ticketNumber),
  })

  return (
    <div className="space-y-4">
      <h2 className="text-base font-semibold text-slate-900">Activity History</h2>

      {isLoading && (
        <div className="p-4 text-center text-xs text-slate-500">Loading history...</div>
      )}

      {isError && (
        <div role="alert" className="rounded-md bg-rose-50 p-4 text-xs text-rose-800">
          <p>Failed to load activity history.</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-2 font-semibold text-rose-600 hover:underline"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && activities && (
        <>
          {activities.length === 0 ? (
            <div className="p-4 text-center text-xs text-slate-500">
              No activity history recorded yet.
            </div>
          ) : (
            <ol className="relative ml-2 space-y-6 border-l border-slate-200 pl-4 text-sm">
              {activities.map((activity) => {
                const actorName = activity.actor?.name ?? 'System'
                const sentence = describeActivity(activity)
                const formattedTime = new Date(activity.created_at).toLocaleString()

                return (
                  <li key={activity.id} className="relative">
                    <span className="absolute -left-6 top-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-blue-600 ring-4 ring-white" />
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <div className="font-medium text-slate-900">{actorName}</div>
                      <time
                        dateTime={activity.created_at}
                        className="text-xs text-slate-500"
                      >
                        {formattedTime}
                      </time>
                    </div>
                    <div className="mt-1 text-sm text-slate-700">{sentence}</div>
                  </li>
                )
              })}
            </ol>
          )}
        </>
      )}
    </div>
  )
}
