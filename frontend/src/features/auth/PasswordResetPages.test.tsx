import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'

describe('Password Reset Pages', () => {
  beforeEach(() => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('shows generic confirmation message on forgot password form submission', async () => {
    const user = userEvent.setup()
    window.history.replaceState(null, '', '/forgot-password')

    server.use(
      http.post('http://api.test/api/v1/auth/forgot-password', () => {
        return HttpResponse.json({
          message: 'If an active account exists for that email, a password reset link has been sent.',
        })
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Email'), 'user@example.test')
    await user.click(screen.getByRole('button', { name: 'Send reset link' }))

    expect(
      await screen.findByText(
        'If an active account exists for that email, a password reset link has been sent.',
      ),
    ).toBeInTheDocument()
  })

  it('successfully resets password and redirects to login with confirmation message', async () => {
    const user = userEvent.setup()
    window.history.replaceState(
      null,
      '',
      '/reset-password?token=valid-token-123&email=user%40example.test',
    )

    server.use(
      http.post('http://api.test/api/v1/auth/reset-password', () => {
        return HttpResponse.json({
          message: 'Your password has been reset.',
        })
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('New password'), 'NewPassword123')
    await user.type(screen.getByLabelText('Confirm new password'), 'NewPassword123')
    await user.click(screen.getByRole('button', { name: 'Update password' }))

    expect(await screen.findByText(/Password updated/i)).toBeInTheDocument()
    expect(window.location.pathname).toBe('/login')
  })
})

