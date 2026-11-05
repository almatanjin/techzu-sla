import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { z } from 'zod'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { resetPassword } from './api'

const resetPasswordSchema = z
  .object({
    email: z.string().min(1, 'Email is required').email('Please enter a valid email address'),
    token: z.string().min(1, 'Token is missing'),
    password: z
      .string()
      .min(8, 'Password must be at least 8 characters')
      .regex(/[A-Za-z]/, 'Password must contain at least one letter')
      .regex(/[0-9]/, 'Password must contain at least one number'),
    password_confirmation: z.string().min(1, 'Password confirmation is required'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const apiClient = useApiClient()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const tokenParam = searchParams.get('token') ?? ''
  const emailParam = searchParams.get('email') ?? ''

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      email: emailParam,
      token: tokenParam,
      password: '',
      password_confirmation: '',
    },
  })

  const onSubmit = async (values: ResetPasswordFormValues) => {
    setFormError(null)
    try {
      await resetPassword(apiClient, values)
      navigate('/login', {
        replace: true,
        state: { message: 'Password updated. Please sign in with your new password.' },
      })
    } catch (error) {
      applyApiErrors(error, setError, setFormError)
    }
  }

  return (
    <div className="mx-auto max-w-md card p-8">
      <h1 className="text-2xl font-bold tracking-tight text-fg">Reset your password</h1>

      {formError && (
        <div role="alert" className="mt-4 alert alert-danger">
          {formError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-4" noValidate>
        <input type="hidden" {...register('token')} />

        <div>
          <label htmlFor="email" className="label">
            Email
          </label>
          <input
            id="email"
            type="email"
            autoComplete="email"
            {...register('email')}
            className="mt-1 input"
          />
          {errors.email && (
            <p className="mt-1 text-sm text-danger">{errors.email.message}</p>
          )}
        </div>

        <div>
          <label htmlFor="password" className="label">
            New password
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

        <div>
          <label htmlFor="password_confirmation" className="label">
            Confirm new password
          </label>
          <input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            {...register('password_confirmation')}
            className="mt-1 input"
          />
          {errors.password_confirmation && (
            <p className="mt-1 text-sm text-danger">{errors.password_confirmation.message}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full btn btn-primary"
        >
          {isSubmitting ? 'Updating password...' : 'Update password'}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-fg-muted">
        <Link to="/login" className="link font-semibold">
          Return to sign in
        </Link>
      </p>
    </div>
  )
}
