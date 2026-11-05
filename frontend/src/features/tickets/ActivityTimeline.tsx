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
      <h2 className="text-base font-semibold text-fg">Activity History</h2>

      {isLoading && (
        <div className="p-4 text-center text-xs text-fg-subtle">Loading history...</div>
      )}

      {isError && (
        <div role="alert" className="alert alert-danger p-4 text-xs">
          <p>Failed to load activity history.</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-2 font-semibold text-danger hover:underline"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && activities && (
        <>
          {activities.length === 0 ? (
            <div className="p-4 text-center text-xs text-fg-subtle">
              No activity history recorded yet.
            </div>
          ) : (
            <ol className="relative ml-2 space-y-6 border-l border-border pl-4 text-sm">
              {activities.map((activity) => {
                const actorName = activity.actor?.name ?? 'System'
                const sentence = describeActivity(activity)
                const formattedTime = new Date(activity.created_at).toLocaleString()

                return (
                  <li key={activity.id} className="relative">
                    <span className="absolute -left-6 top-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-accent ring-4 ring-surface" />
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <div className="font-medium text-fg">{actorName}</div>
                      <time
                        dateTime={activity.created_at}
                        className="text-xs text-fg-subtle"
                      >
                        {formattedTime}
                      </time>
                    </div>
                    <div className="mt-1 text-sm text-fg-secondary">{sentence}</div>
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
