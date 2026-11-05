import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { useActiveCategories } from '../categories/useActiveCategories'
import { updateTicket } from './api'
import { priorityLabels } from './labels'
import type { Ticket, TicketPriority, UpdateTicketPayload } from './types'

type FormValues = {
  subject: string
  description: string
  priority: TicketPriority
  category_id: number | ''
}

type Props = {
  ticket: Ticket
  onTicketUpdated?: (ticket: Ticket) => void
  onConflict?: (conflictTicket: Ticket, message: string) => void
}

export function TicketEditForm({ ticket, onTicketUpdated, onConflict }: Props) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const { data: categories } = useActiveCategories()
  const [formError, setFormError] = useState<string | null>(null)

  const canEditContent = ticket.permissions.can_edit_content
  const canEditClassification = ticket.permissions.can_edit_classification

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    defaultValues: {
      subject: ticket.subject,
      description: ticket.description,
      priority: ticket.priority,
      category_id: ticket.category.id,
    },
  })

  // Synchronize form when ticket updates (e.g. after conflict or external refresh)
  useEffect(() => {
    reset({
      subject: ticket.subject,
      description: ticket.description,
      priority: ticket.priority,
      category_id: ticket.category.id,
    })
  }, [ticket, reset])

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload: UpdateTicketPayload = {
        lock_version: ticket.lock_version,
      }

      if (canEditContent) {
        payload.subject = values.subject.trim()
        payload.description = values.description.trim()
      }

      if (canEditClassification) {
        payload.priority = values.priority
        payload.category_id = Number(values.category_id)
      }

      return updateTicket(apiClient, ticket.ticket_number, payload)
    },
    onSuccess: async (updated) => {
      setFormError(null)
      queryClient.setQueryData(['tickets', updated.ticket_number], updated)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tickets'] }),
        queryClient.invalidateQueries({
          queryKey: ['tickets', updated.ticket_number, 'activities'],
        }),
      ])
      onTicketUpdated?.(updated)
    },
    onError: (err: unknown) => {
      const anyErr = err as {
        response?: { status?: number; data?: { message?: string; ticket?: Ticket } }
      }

      if (anyErr?.response?.status === 409 && anyErr.response.data?.ticket) {
        const conflictTicket = anyErr.response.data.ticket
        const message =
          anyErr.response.data.message ??
          'This ticket was changed by someone else. The latest version is now shown.'
        queryClient.setQueryData(['tickets', conflictTicket.ticket_number], conflictTicket)
        reset({
          subject: conflictTicket.subject,
          description: conflictTicket.description,
          priority: conflictTicket.priority,
          category_id: conflictTicket.category.id,
        })
        onConflict?.(conflictTicket, message)
        return
      }

      applyApiErrors<FormValues>(err, setError, setFormError)
    },
  })

  if (!canEditContent && !canEditClassification) {
    return null
  }

  return (
    <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
      {formError && (
        <div role="alert" className="alert alert-danger p-3 text-xs">
          {formError}
        </div>
      )}

      {canEditContent ? (
        <>
          <div>
            <label htmlFor="edit-subject" className="label">
              Subject
            </label>
            <input
              id="edit-subject"
              type="text"
              maxLength={200}
              {...register('subject', { required: 'Subject is required' })}
              className="mt-1 input"
            />
            {errors.subject && (
              <p role="alert" className="mt-1 text-xs text-danger">
                {errors.subject.message}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="edit-description" className="label">
              Description
            </label>
            <textarea
              id="edit-description"
              rows={5}
              maxLength={10000}
              {...register('description', { required: 'Description is required' })}
              className="mt-1 input"
            />
            {errors.description && (
              <p role="alert" className="mt-1 text-xs text-danger">
                {errors.description.message}
              </p>
            )}
          </div>
        </>
      ) : (
        <div className="rounded-md bg-canvas p-4 text-sm text-fg-secondary whitespace-pre-wrap border border-border">
          {ticket.description}
        </div>
      )}

      {canEditClassification && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="edit-priority" className="label">
              Priority
            </label>
            <select
              id="edit-priority"
              {...register('priority')}
              className="mt-1 input"
            >
              {(['low', 'medium', 'high', 'critical'] as TicketPriority[]).map((p) => (
                <option key={p} value={p}>
                  {priorityLabels[p]}
                </option>
              ))}
            </select>
            {errors.priority && (
              <p role="alert" className="mt-1 text-xs text-danger">
                {errors.priority.message}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="edit-category" className="label">
              Category
            </label>
            <select
              id="edit-category"
              {...register('category_id')}
              className="mt-1 input"
            >
              {categories?.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
            {errors.category_id && (
              <p role="alert" className="mt-1 text-xs text-danger">
                {errors.category_id.message}
              </p>
            )}
          </div>
        </div>
      )}

      <div className="flex justify-end pt-2">
        <button
          type="submit"
          disabled={isSubmitting || mutation.isPending}
          className="btn btn-primary"
        >
          {isSubmitting || mutation.isPending ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </form>
  )
}
