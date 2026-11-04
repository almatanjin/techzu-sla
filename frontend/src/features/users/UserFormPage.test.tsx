import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('UserFormPage', () => {
  beforeEach(() => {
    signIn({ role: 'admin' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/admin/users', () => {
        return HttpResponse.json({
          data: [],
          links: {},
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('creates a user and returns to the users list', async () => {
    const user = userEvent.setup()
    let createdPayload: unknown = null

    server.use(
      http.post('http://api.test/api/v1/admin/users', async ({ request }) => {
        createdPayload = await request.json()
        return HttpResponse.json(
          {
            id: 25,
            name: 'New Agent',
            email: 'newagent@example.test',
            role: 'agent',
            is_active: true,
            created_at: '2026-01-01T00:00:00Z',
          },
          { status: 201 },
        )
      }),
    )

    window.history.replaceState(null, '', '/admin/users/new')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Create New User' })).toBeInTheDocument()

    await user.type(screen.getByLabelText('Full name'), 'New Agent')
    await user.type(screen.getByLabelText('Email address'), 'newagent@example.test')
    await user.selectOptions(screen.getByLabelText('Role'), 'agent')
    await user.type(screen.getByLabelText('Initial password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Create user' }))

    expect(await screen.findByRole('heading', { name: 'Users' })).toBeInTheDocument()
    expect(window.location.pathname).toBe('/admin/users')
    expect(createdPayload).toMatchObject({
      name: 'New Agent',
      email: 'newagent@example.test',
      role: 'agent',
      password: 'Password123',
      is_active: true,
    })
  })

  it('displays API validation error under the email field on duplicate email', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/admin/users', () => {
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

    window.history.replaceState(null, '', '/admin/users/new')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Create New User' })).toBeInTheDocument()

    await user.type(screen.getByLabelText('Full name'), 'Duplicate Agent')
    await user.type(screen.getByLabelText('Email address'), 'duplicate@example.test')
    await user.type(screen.getByLabelText('Initial password'), 'Password123')
    await user.click(screen.getByRole('button', { name: 'Create user' }))

    expect(
      await screen.findByText('The email has already been taken.'),
    ).toBeInTheDocument()
    expect(window.location.pathname).toBe('/admin/users/new')
  })
})
