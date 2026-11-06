import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { delay, http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('LoginPage', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/login')
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('signs in successfully and displays the user name in the header', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/auth/login', () => {
        return HttpResponse.json({
          token: 'token-abc',
          token_type: 'Bearer',
          expires_at: '2026-11-12T00:00:00Z',
          user: {
            id: 42,
            name: 'Alice Wonder',
            email: 'alice@example.test',
            role: 'customer',
            is_active: true,
          },
        })
      }),
      http.get('http://api.test/api/v1/auth/me', () => {
        return HttpResponse.json({
          id: 42,
          name: 'Alice Wonder',
          email: 'alice@example.test',
          role: 'customer',
          is_active: true,
        })
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.test')
    await user.type(screen.getByLabelText('Password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Alice Wonder')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/')
  })

  it('returns to the originally requested page, including its query string, after signing in', async () => {
    const user = userEvent.setup()
    const adminUser = {
      id: 1,
      name: 'Demo Admin',
      email: 'admin@example.test',
      role: 'admin',
      is_active: true,
    }

    server.use(
      http.post('http://api.test/api/v1/auth/login', () =>
        HttpResponse.json({
          token: 'token-admin',
          token_type: 'Bearer',
          expires_at: '2026-11-12T00:00:00Z',
          user: adminUser,
        }),
      ),
      http.get('http://api.test/api/v1/auth/me', () => HttpResponse.json(adminUser)),
      http.get('http://api.test/api/v1/admin/users', () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
          links: { first: null, last: null, prev: null, next: null },
        }),
      ),
    )

    window.history.replaceState(null, '', '/admin/users?role=agent')
    render(<Root />)

    await user.type(await screen.findByLabelText('Email'), 'admin@example.test')
    await user.type(screen.getByLabelText('Password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByRole('heading', { name: 'Users' })).toBeInTheDocument()
    expect(window.location.pathname).toBe('/admin/users')
    expect(window.location.search).toBe('?role=agent')
  })

  it('shows API error message under email field on wrong password', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/auth/login', () => {
        return HttpResponse.json(
          {
            message: 'These credentials do not match our records.',
            errors: {
              email: ['These credentials do not match our records.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.test')
    await user.type(screen.getByLabelText('Password'), 'WrongPassword')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign in' })).not.toBeDisabled()
  })

  it('disables submit button while login is pending', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/auth/login', async () => {
        await delay(100)
        return HttpResponse.json({
          token: 'token-delay',
          token_type: 'Bearer',
          expires_at: '2026-11-12T00:00:00Z',
          user: {
            id: 1,
            name: 'Pending User',
            email: 'pending@example.test',
            role: 'customer',
            is_active: true,
          },
        })
      }),
    )

    render(<Root />)

    await user.type(screen.getByLabelText('Email'), 'pending@example.test')
    await user.type(screen.getByLabelText('Password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    const pendingButton = screen.getByRole('button', { name: 'Signing in...' })
    expect(pendingButton).toBeDisabled()
  })

  it('displays session-ended message when redirected to login after a 401', async () => {
    signIn()
    window.history.replaceState(null, '', '/')

    server.use(
      http.get('http://api.test/api/v1/health', () => {
        return HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })
      }),
    )

    render(<Root />)

    await waitFor(() => {
      expect(
        screen.getByText('Your session has ended. Please sign in again.'),
      ).toBeInTheDocument()
      expect(window.location.pathname).toBe('/login')
    })
  })
})

