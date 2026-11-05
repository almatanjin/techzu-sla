import { useInfiniteQuery } from '@tanstack/react-query'
import { useApiClient } from '../../shared/api/apiClientContext'
import { MessageItem } from './MessageItem'
import { fetchMessages, type MessagesResponse } from './api'
import type { TicketMessage } from './types'

type ConversationThreadProps = {
  ticketNumber: string
}

export function ConversationThread({ ticketNumber }: ConversationThreadProps) {
  const apiClient = useApiClient()

  const {
    data,
    isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery<MessagesResponse>({
    queryKey: ['ticket-messages', ticketNumber],
    queryFn: ({ pageParam }) =>
      fetchMessages(apiClient, ticketNumber, pageParam as string | null),
    initialPageParam: null,
    getNextPageParam: (lastPage) => lastPage.meta?.next_cursor ?? null,
  })

  if (isLoading) {
    return <div className="p-4 text-center text-xs text-fg-subtle">Loading conversation...</div>
  }

  if (isError) {
    return (
      <div role="alert" className="alert alert-danger p-3 text-xs text-center">
        Failed to load conversation.
      </div>
    )
  }

  // Each page from the API is newest first.
  // Reversing each page and prepending older pages places the oldest message at the top.
  const pages = data?.pages ?? []
  const allMessages: TicketMessage[] = []

  // pages[0] has newest 30 messages, pages[1] has older messages, etc.
  // We iterate pages backwards (older first), and inside each page reverse to get oldest first.
  for (let i = pages.length - 1; i >= 0; i--) {
    const pageMessages = [...(pages[i].data ?? [])].reverse()
    allMessages.push(...pageMessages)
  }

  return (
    <div className="space-y-4">
      {hasNextPage && (
        <div className="text-center pt-1">
          <button
            type="button"
            onClick={() => void fetchNextPage()}
            disabled={isFetchingNextPage}
            className="btn btn-sm btn-outline text-xs"
          >
            {isFetchingNextPage ? 'Loading earlier messages...' : 'Show earlier messages'}
          </button>
        </div>
      )}

      {allMessages.length === 0 ? (
        <div className="py-6 text-center text-xs text-fg-subtle">No messages yet.</div>
      ) : (
        <div className="space-y-3">
          {allMessages.map((msg) => (
            <MessageItem key={msg.id} message={msg} />
          ))}
        </div>
      )}
    </div>
  )
}

