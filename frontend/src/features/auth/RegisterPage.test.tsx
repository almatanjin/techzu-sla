import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'

describe('RegisterPage', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/register')
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('creates an account successfully and navigates to the home page', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/auth/register', () => {
        return HttpResponse.json(
          {
            token: 'reg-token-123',
            token_type: 'Bearer',
            expires_at: '2026-11-12T00:00:00Z',
            user: {
              id: 99,
              name: 'Registered Customer',
              email: 'customer@example.test',
              role: 'customer',
              is_active: true,
            },
          },
          { status: 201 },
        )
      }),
      http.get('http://api.test/api/v1/auth/me', () => {
        return HttpResponse.json({
          id: 99,
          name: 'Registered Customer',
          email: 'customer@example.test',
          role: 'customer',
          is_active: true,
        })
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Full name'), 'Registered Customer')
    await user.type(screen.getByLabelText('Email'), 'customer@example.test')
    await user.type(screen.getByLabelText('Password'), 'Password123')
    await user.type(screen.getByLabelText('Confirm password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('Registered Customer')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/')
  })

  it('shows duplicate email error under the email field on 422', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/auth/register', () => {
        return HttpResponse.json(
          {
            message: 'The email has already been taken.',
            errors: {
              email: ['The email has already been taken.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Full name'), 'Duplicate User')
    await user.type(screen.getByLabelText('Email'), 'taken@example.test')
    await user.type(screen.getByLabelText('Password'), 'Password123')
    await user.type(screen.getByLabelText('Confirm password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(
      await screen.findByText('The email has already been taken.'),
    ).toBeInTheDocument()
    expect(window.location.pathname).toBe('/register')
  })
})

