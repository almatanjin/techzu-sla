import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { useAuth } from '../auth/useAuth'
import { useActiveCategories } from '../categories/useActiveCategories'
import { CustomerPicker } from './CustomerPicker'
import { createTicket } from './api'
import { priorityLabels } from './labels'
import type { CreateTicketPayload, TicketPriority } from './types'

type FormValues = {
  subject: string
  description: string
  category_id: number | ''
  priority: TicketPriority
  customer_id: number | null
}

export function NewTicketPage() {
  const { user } = useAuth()
  const apiClient = useApiClient()
  const navigate = useNavigate()
  const isStaff = user?.role === 'admin' || user?.role === 'agent'

  const { data: categories, isLoading: loadingCategories } = useActiveCategories()
  const [formError, setFormError] = useState<string | null>(null)
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    defaultValues: {
      subject: '',
      description: '',
      category_id: '',
      priority: 'medium',
      customer_id: null,
    },
  })

  const onSubmit = async (values: FormValues) => {
    setFormError(null)

    if (isStaff && !selectedCustomerId) {
      setError('customer_id', {
        type: 'manual',
        message: 'Please select a customer for this ticket.',
      })
      return
    }

    if (!values.category_id) {
      setError('category_id', {
        type: 'manual',
        message: 'Please select a category.',
      })
      return
    }

    const payload: CreateTicketPayload = {
      subject: values.subject.trim(),
      description: values.description.trim(),
      category_id: Number(values.category_id),
      priority: values.priority,
    }

    if (isStaff && selectedCustomerId) {
      payload.customer_id = selectedCustomerId
    }

    try {
      const created = await createTicket(apiClient, payload)
      navigate(`/tickets/${created.ticket_number}`)
    } catch (err) {
      applyApiErrors<FormValues>(err, setError, setFormError)
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-fg">New Ticket</h1>
          <p className="mt-1 text-sm text-fg-muted">
            Open a support ticket for technical assistance or billing questions.
          </p>
        </div>
        <Link
          to="/tickets"
          className="link-muted text-sm font-medium"
        >
          Cancel
        </Link>
      </div>

      <div className="card p-6">
        {formError && (
          <div role="alert" className="mb-6 alert alert-danger">
            {formError}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Customer picker (staff only) */}
          {isStaff && (
            <CustomerPicker
              value={selectedCustomerId}
              onChange={(id) => setSelectedCustomerId(id)}
              error={errors.customer_id?.message}
            />
          )}

          {/* Category */}
          <div>
            <label htmlFor="ticket-category" className="label">
              Category
            </label>
            <select
              id="ticket-category"
              disabled={loadingCategories}
              {...register('category_id', { required: 'Please select a category' })}
              className="mt-1 input"
            >
              <option value="">Select a category</option>
              {categories?.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
            {errors.category_id && (
              <p role="alert" className="mt-1.5 text-xs text-danger">
                {errors.category_id.message}
              </p>
            )}
          </div>

          {/* Priority (staff only) */}
          {isStaff && (
            <div>
              <label htmlFor="ticket-priority" className="label">
                Priority
              </label>
              <select
                id="ticket-priority"
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
                <p role="alert" className="mt-1.5 text-xs text-danger">
                  {errors.priority.message}
                </p>
              )}
            </div>
          )}

          {/* Subject */}
          <div>
            <label htmlFor="ticket-subject" className="label">
              Subject
            </label>
            <input
              id="ticket-subject"
              type="text"
              maxLength={200}
              placeholder="Brief summary of the issue"
              {...register('subject', { required: 'Subject is required' })}
              className="mt-1 input"
            />
            {errors.subject && (
              <p role="alert" className="mt-1.5 text-xs text-danger">
                {errors.subject.message}
              </p>
            )}
          </div>

          {/* Description */}
          <div>
            <label htmlFor="ticket-description" className="label">
              Description
            </label>
            <textarea
              id="ticket-description"
              rows={6}
              maxLength={10000}
              placeholder="Provide all relevant details to help us resolve the problem..."
              {...register('description', { required: 'Description is required' })}
              className="mt-1 input"
            />
            {errors.description && (
              <p role="alert" className="mt-1.5 text-xs text-danger">
                {errors.description.message}
              </p>
            )}
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <Link
              to="/tickets"
              className="btn btn-outline"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={isSubmitting}
              className="btn btn-primary"
            >
              {isSubmitting ? 'Submitting...' : 'Submit Ticket'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
