import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router'
import { z } from 'zod'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { AgentSettingsFields } from '../assignment/AgentSettingsFields'
import { createUser, getUser, updateUser } from './api'

const baseUserSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  email: z.string().min(1, 'Email is required').email('Please enter a valid email address'),
  role: z.enum(['admin', 'agent', 'customer']),
  password: z.string().optional(),
  is_active: z.boolean(),
  is_available: z.boolean().optional(),
  max_open_tickets: z.union([z.string(), z.number(), z.null()]).optional(),
  category_ids: z.array(z.number()).optional(),
})

type UserFormValues = z.infer<typeof baseUserSchema>

export function UserFormPage() {
  const { id } = useParams<{ id?: string }>()
  const isEditMode = Boolean(id)
  const userId = id ? Number(id) : null

  const apiClient = useApiClient()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const validationSchema = baseUserSchema.superRefine((data, ctx) => {
    if (!isEditMode) {
      if (!data.password || data.password.length < 8) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'Password must be at least 8 characters',
          path: ['password'],
        })
      } else if (!/[A-Za-z]/.test(data.password) || !/[0-9]/.test(data.password)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'Password must contain at least one letter and one number',
          path: ['password'],
        })
      }
    } else if (data.password && data.password.length > 0) {
      if (data.password.length < 8 || !/[A-Za-z]/.test(data.password) || !/[0-9]/.test(data.password)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'Password must be at least 8 characters and contain letters and numbers',
          path: ['password'],
        })
      }
    }
  })

  const {
    register,
    handleSubmit,
    reset,
    setError,
    control,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<UserFormValues>({
    resolver: zodResolver(validationSchema),
    defaultValues: {
      name: '',
      email: '',
      role: 'customer',
      password: '',
      is_active: true,
      is_available: true,
      max_open_tickets: '',
      category_ids: [],
    },
  })

  const watchedRole = useWatch({ control, name: 'role' })
  const isAvailable = useWatch({ control, name: 'is_available' }) ?? true
  const maxOpenTickets = String(useWatch({ control, name: 'max_open_tickets' }) ?? '')
  const categoryIds = (useWatch({ control, name: 'category_ids' }) as number[]) ?? []

  const { data: existingUser, isLoading: isLoadingUser } = useQuery({
    queryKey: ['admin', 'users', userId],
    queryFn: () => (userId ? getUser(apiClient, userId) : Promise.reject('No ID')),
    enabled: isEditMode && userId !== null,
  })

  useEffect(() => {
    if (existingUser) {
      reset({
        name: existingUser.name,
        email: existingUser.email,
        role: existingUser.role,
        password: '',
        is_active: existingUser.is_active,
        is_available: existingUser.agent_settings?.is_available ?? true,
        max_open_tickets:
          existingUser.agent_settings?.max_open_tickets !== null &&
          existingUser.agent_settings?.max_open_tickets !== undefined
            ? String(existingUser.agent_settings.max_open_tickets)
            : '',
        category_ids: existingUser.agent_settings?.category_ids ?? [],
      })
    }
  }, [existingUser, reset])

  const onSubmit = async (values: UserFormValues) => {
    setFormError(null)
    try {
      if (isEditMode && userId !== null) {
        await updateUser(apiClient, userId, {
          name: values.name,
          email: values.email,
          role: values.role,
          is_active: values.is_active,
          ...(values.password ? { password: values.password } : {}),
          ...(values.role === 'agent'
            ? {
                is_available: values.is_available ?? true,
                max_open_tickets:
                  values.max_open_tickets === '' ||
                  values.max_open_tickets === null ||
                  values.max_open_tickets === undefined
                    ? null
                    : Number(values.max_open_tickets),
                category_ids: values.category_ids ?? [],
              }
            : {}),
        })
      } else {
        await createUser(apiClient, {
          name: values.name,
          email: values.email,
          role: values.role,
          password: values.password ?? '',
          is_active: values.is_active,
        })
      }
      navigate('/admin/users', { replace: true })
    } catch (error) {
      applyApiErrors(error, setError, setFormError)
    }
  }

  if (isEditMode && isLoadingUser) {
    return (
      <div className="card p-12 text-center text-fg-subtle">
        Loading user details...
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-lg card p-8">
      <div className="mb-6">
        <h1 className="text-2xl font-bold tracking-tight text-fg">
          {isEditMode ? 'Edit User' : 'Create New User'}
        </h1>
        <p className="mt-1 text-sm text-fg-muted">
          {isEditMode
            ? 'Update user account information and roles.'
            : 'Add a new administrator, support agent, or customer.'}
        </p>
      </div>

      {formError && (
        <div role="alert" className="mb-6 alert alert-danger">
          {formError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <div>
          <label htmlFor="name" className="label">
            Full name
          </label>
          <input
            id="name"
            type="text"
            {...register('name')}
            className="mt-1 input"
          />
          {errors.name && (
            <p className="mt-1 text-sm text-danger">{errors.name.message}</p>
          )}
        </div>

        <div>
          <label htmlFor="email" className="label">
            Email address
          </label>
          <input
            id="email"
            type="email"
            {...register('email')}
            className="mt-1 input"
          />
          {errors.email && (
            <p className="mt-1 text-sm text-danger">{errors.email.message}</p>
          )}
        </div>

        <div>
          <label htmlFor="role" className="label">
            Role
          </label>
          <select
            id="role"
            {...register('role')}
            className="mt-1 input"
          >
            <option value="customer">Customer</option>
            <option value="agent">Support Agent</option>
            <option value="admin">Administrator</option>
          </select>
          {errors.role && (
            <p className="mt-1 text-sm text-danger">{errors.role.message}</p>
          )}
        </div>

        <div>
          <label htmlFor="password" className="label">
            {isEditMode ? 'New password (leave blank to keep unchanged)' : 'Initial password'}
          </label>
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            {...register('password')}
            className="mt-1 input"
          />
          {errors.password && (
            <p className="mt-1 text-sm text-danger">{errors.password.message}</p>
          )}
        </div>

        <div className="flex items-center gap-3 pt-2">
          <input
            id="is_active"
            type="checkbox"
            {...register('is_active')}
            className="checkbox"
          />
          <label htmlFor="is_active" className="text-sm font-medium text-fg-secondary">
            Active user account (can sign in)
          </label>
        </div>

        {watchedRole === 'agent' && (
          <AgentSettingsFields
            isAvailable={isAvailable}
            onIsAvailableChange={(val) => setValue('is_available', val, { shouldDirty: true })}
            maxOpenTickets={maxOpenTickets}
            onMaxOpenTicketsChange={(val) => setValue('max_open_tickets', val, { shouldDirty: true })}
            categoryIds={categoryIds}
            onCategoryIdsChange={(val) => setValue('category_ids', val, { shouldDirty: true })}
          />
        )}

        <div className="flex items-center justify-end gap-3 pt-4">
          <Link
            to="/admin/users"
            className="btn btn-outline"
          >
            Cancel
          </Link>
          <button
            type="submit"
            disabled={isSubmitting}
            className="btn btn-primary"
          >
            {isSubmitting ? 'Saving...' : isEditMode ? 'Update user' : 'Create user'}
          </button>
        </div>
      </form>
    </div>
  )
}
