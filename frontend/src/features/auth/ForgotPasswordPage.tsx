import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { forgotPassword } from './api'

const forgotPasswordSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Please enter a valid email address'),
})

type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

export function ForgotPasswordPage() {
  const apiClient = useApiClient()
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordFormValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
  })

  const onSubmit = async (values: ForgotPasswordFormValues) => {
    setFormError(null)
    setSuccessMessage(null)
    try {
      const response = await forgotPassword(apiClient, values)
      setSuccessMessage(response.message)
    } catch (error) {
      applyApiErrors(error, setError, setFormError)
    }
  }

  return (
    <div className="mx-auto max-w-md card p-8">
      <h1 className="text-2xl font-bold tracking-tight text-fg">Forgot your password?</h1>
      <p className="mt-2 text-sm text-fg-muted">
        Enter your email address and we will send you a link to reset your password.
      </p>

      {successMessage && (
        <div role="status" className="mt-4 alert alert-success">
          {successMessage}
        </div>
      )}

      {formError && (
        <div role="alert" className="mt-4 alert alert-danger">
          {formError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-4" noValidate>
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

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full btn btn-primary"
        >
          {isSubmitting ? 'Sending link...' : 'Send reset link'}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-fg-muted">
        Remember your password?{' '}
        <Link to="/login" className="link font-semibold">
          Return to sign in
        </Link>
      </p>
    </div>
  )
}
