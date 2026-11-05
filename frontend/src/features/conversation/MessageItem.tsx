import { AttachmentList } from './AttachmentList'
import type { TicketMessage } from './types'

type MessageItemProps = {
  message: TicketMessage
}

export function MessageItem({ message }: MessageItemProps) {
  const isInternalNote = message.type === 'internal_note'
  const timeFormatted = new Date(message.created_at).toLocaleString()

  return (
    <div
      className={`rounded-lg border p-4 transition-colors ${
        isInternalNote
          ? 'border-warning-border bg-warning-subtle text-fg'
          : 'border-border-subtle bg-surface text-fg'
      }`}
    >
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle/50 pb-2 mb-3">
        <div className="flex items-center gap-2">
          <span className="font-semibold text-sm text-fg">{message.author.name}</span>
          <span className="text-xs text-fg-subtle capitalize">({message.author.role})</span>
          {isInternalNote && (
            <span className="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold bg-warning-soft text-warning-fg border border-warning-border">
              Internal note
            </span>
          )}
        </div>
        <time dateTime={message.created_at} className="text-xs text-fg-subtle">
          {timeFormatted}
        </time>
      </div>

      {message.body && (
        <div className="whitespace-pre-wrap text-sm text-fg leading-relaxed">
          {message.body}
        </div>
      )}

      {message.attachments && message.attachments.length > 0 && (
        <AttachmentList attachments={message.attachments} />
      )}
    </div>
  )
}

