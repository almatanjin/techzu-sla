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
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">New Ticket</h1>
          <p className="mt-1 text-sm text-slate-600">
            Open a support ticket for technical assistance or billing questions.
          </p>
        </div>
        <Link
          to="/tickets"
          className="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline"
        >
          Cancel
        </Link>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        {formError && (
          <div role="alert" className="mb-6 rounded-md bg-rose-50 p-4 text-sm text-rose-800">
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
            <label htmlFor="ticket-category" className="block text-sm font-medium text-slate-700">
              Category
            </label>
            <select
              id="ticket-category"
              disabled={loadingCategories}
              {...register('category_id', { required: 'Please select a category' })}
              className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Select a category</option>
              {categories?.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
            {errors.category_id && (
              <p role="alert" className="mt-1.5 text-xs text-rose-600">
                {errors.category_id.message}
              </p>
            )}
          </div>

          {/* Priority (staff only) */}
          {isStaff && (
            <div>
              <label htmlFor="ticket-priority" className="block text-sm font-medium text-slate-700">
                Priority
              </label>
              <select
                id="ticket-priority"
                {...register('priority')}
                className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                {(['low', 'medium', 'high', 'critical'] as TicketPriority[]).map((p) => (
                  <option key={p} value={p}>
                    {priorityLabels[p]}
                  </option>
                ))}
              </select>
              {errors.priority && (
                <p role="alert" className="mt-1.5 text-xs text-rose-600">
                  {errors.priority.message}
                </p>
              )}
            </div>
          )}

          {/* Subject */}
          <div>
            <label htmlFor="ticket-subject" className="block text-sm font-medium text-slate-700">
              Subject
            </label>
            <input
              id="ticket-subject"
              type="text"
              maxLength={200}
              placeholder="Brief summary of the issue"
              {...register('subject', { required: 'Subject is required' })}
              className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {errors.subject && (
              <p role="alert" className="mt-1.5 text-xs text-rose-600">
                {errors.subject.message}
              </p>
            )}
          </div>

          {/* Description */}
          <div>
            <label htmlFor="ticket-description" className="block text-sm font-medium text-slate-700">
              Description
            </label>
            <textarea
              id="ticket-description"
              rows={6}
              maxLength={10000}
              placeholder="Provide all relevant details to help us resolve the problem..."
              {...register('description', { required: 'Description is required' })}
              className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {errors.description && (
              <p role="alert" className="mt-1.5 text-xs text-rose-600">
                {errors.description.message}
              </p>
            )}
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <Link
              to="/tickets"
              className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={isSubmitting}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {isSubmitting ? 'Submitting...' : 'Submit Ticket'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
