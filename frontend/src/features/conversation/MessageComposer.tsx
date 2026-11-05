import { useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { validateAttachmentFiles } from '../../shared/attachments'
import { FilePicker } from '../../shared/components/FilePicker'
import { postMessage } from './api'
import type { MessageType } from './types'

type MessageComposerProps = {
  ticketNumber: string
  canAddInternalNote: boolean
}

export function MessageComposer({ ticketNumber, canAddInternalNote }: MessageComposerProps) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()

  const [type, setType] = useState<MessageType>('reply')
  const [body, setBody] = useState('')
  const [files, setFiles] = useState<File[]>([])
  const [fileError, setFileError] = useState<string | null>(null)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleFilesChange = (newFiles: File[]) => {
    setFiles(newFiles)
    const err = validateAttachmentFiles(newFiles)
    setFileError(err)
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setSubmitError(null)

    const clientFileError = validateAttachmentFiles(files)
    if (clientFileError) {
      setFileError(clientFileError)
      return
    }

    if (!body.trim() && files.length === 0) {
      return
    }

    setIsSubmitting(true)
    try {
      await postMessage(apiClient, ticketNumber, {
        type,
        body,
        files,
      })

      setBody('')
      setFiles([])
      setFileError(null)

      // Refresh thread and activities
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['ticket-messages', ticketNumber] }),
        queryClient.invalidateQueries({ queryKey: ['ticket-activities', ticketNumber] }),
        queryClient.invalidateQueries({ queryKey: ['tickets', ticketNumber] }),
      ])
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: {
          status?: number
          data?: {
            message?: string
            errors?: Record<string, string[]>
          }
        }
      }

      const errors = axiosErr.response?.data?.errors
      let hasFieldError = false
      if (errors) {
        if (errors['attachments.0']) {
          setFileError(errors['attachments.0'][0])
          hasFieldError = true
        } else if (errors['attachments']) {
          setFileError(errors['attachments'][0])
          hasFieldError = true
        }
      }

      if (!hasFieldError) {
        setSubmitError(
          axiosErr.response?.data?.message ?? 'Failed to send message. Please try again.',
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const isInternalNote = type === 'internal_note'
  const submitLabel = isInternalNote ? 'Add note' : 'Send reply'

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-border p-4 bg-surface">
      {canAddInternalNote && (
        <div className="flex items-center gap-4 text-xs font-semibold">
          <label className="inline-flex items-center gap-1.5 cursor-pointer">
            <input
              type="radio"
              name="message_type"
              value="reply"
              checked={type === 'reply'}
              onChange={() => setType('reply')}
              className="text-primary focus:ring-primary"
            />
            <span className="text-fg">Reply</span>
          </label>
          <label className="inline-flex items-center gap-1.5 cursor-pointer">
            <input
              type="radio"
              name="message_type"
              value="internal_note"
              checked={type === 'internal_note'}
              onChange={() => setType('internal_note')}
              className="text-warning focus:ring-warning"
            />
            <span className="text-fg">Internal note</span>
          </label>
        </div>
      )}

      {submitError && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {submitError}
        </div>
      )}

      <div>
        <textarea
          rows={3}
          value={body}
          disabled={isSubmitting}
          onChange={(e) => setBody(e.target.value)}
          placeholder={isInternalNote ? 'Write an internal note...' : 'Write a reply...'}
          className={`input w-full resize-y text-sm ${
            isInternalNote ? 'border-warning-border focus:border-warning' : ''
          }`}
          aria-label={isInternalNote ? 'Internal note body' : 'Reply body'}
        />
      </div>

      <div className="flex flex-wrap items-center justify-between gap-4">
        <FilePicker
          files={files}
          onChange={handleFilesChange}
          error={fileError}
          disabled={isSubmitting}
        />

        <button
          type="submit"
          disabled={isSubmitting || (!body.trim() && files.length === 0) || Boolean(fileError)}
          className={`btn btn-sm ${isInternalNote ? 'btn-warning' : 'btn-primary'}`}
        >
          {isSubmitting ? 'Sending...' : submitLabel}
        </button>
      </div>
    </form>
  )
}
