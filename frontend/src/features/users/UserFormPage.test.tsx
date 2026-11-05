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
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({ data: [] })
      }),
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

  it('shows assignment section when editing an agent and sends updated settings on submit', async () => {
    const user = userEvent.setup()
    let patchPayload: unknown = null

    server.use(
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({
          data: [
            { id: 1, name: 'Billing', is_active: true },
            { id: 2, name: 'Technical', is_active: true },
          ],
        })
      }),
      http.get('http://api.test/api/v1/admin/users/10', () => {
        return HttpResponse.json({
          id: 10,
          name: 'Rafi Agent',
          email: 'rafi@example.test',
          role: 'agent',
          is_active: true,
          agent_settings: {
            is_available: true,
            max_open_tickets: 10,
            category_ids: [2],
          },
        })
      }),
      http.patch('http://api.test/api/v1/admin/users/10', async ({ request }) => {
        patchPayload = await request.json()
        return HttpResponse.json({
          id: 10,
          name: 'Rafi Agent',
          email: 'rafi@example.test',
          role: 'agent',
          is_active: true,
          agent_settings: {
            is_available: false,
            max_open_tickets: 5,
            category_ids: [1, 2],
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/users/10/edit')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Edit User' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Assignment' })).toBeInTheDocument()

    // Untick "Available for new tickets"
    const availableCheckbox = screen.getByLabelText(/available for new tickets/i)
    expect(availableCheckbox).toBeChecked()
    await user.click(availableCheckbox)

    // Enter 5 for "Maximum active tickets"
    const maxTicketsInput = screen.getByLabelText(/maximum active tickets/i)
    await user.clear(maxTicketsInput)
    await user.type(maxTicketsInput, '5')

    // Tick "Billing"
    const billingCheckbox = screen.getByLabelText('Billing')
    expect(billingCheckbox).not.toBeChecked()
    await user.click(billingCheckbox)

    // Save
    await user.click(screen.getByRole('button', { name: 'Update user' }))

    expect(await screen.findByRole('heading', { name: 'Users' })).toBeInTheDocument()
    expect(patchPayload).toMatchObject({
      is_available: false,
      max_open_tickets: 5,
      category_ids: expect.arrayContaining([1, 2]),
    })
  })

  it('sends null for empty maximum active tickets', async () => {
    const user = userEvent.setup()
    let patchPayload: unknown = null

    server.use(
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({ data: [] })
      }),
      http.get('http://api.test/api/v1/admin/users/11', () => {
        return HttpResponse.json({
          id: 11,
          name: 'Agent With Max',
          email: 'withmax@example.test',
          role: 'agent',
          is_active: true,
          agent_settings: {
            is_available: true,
            max_open_tickets: 15,
            category_ids: [],
          },
        })
      }),
      http.patch('http://api.test/api/v1/admin/users/11', async ({ request }) => {
        patchPayload = await request.json()
        return HttpResponse.json({
          id: 11,
          name: 'Agent With Max',
          email: 'withmax@example.test',
          role: 'agent',
          is_active: true,
          agent_settings: {
            is_available: true,
            max_open_tickets: null,
            category_ids: [],
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/users/11/edit')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Edit User' })).toBeInTheDocument()
    const maxTicketsInput = screen.getByLabelText(/maximum active tickets/i)
    await user.clear(maxTicketsInput)

    await user.click(screen.getByRole('button', { name: 'Update user' }))

    expect(await screen.findByRole('heading', { name: 'Users' })).toBeInTheDocument()
    expect(patchPayload).toMatchObject({
      max_open_tickets: null,
    })
  })

  it('hides assignment section for customers and reveals it when role is switched to agent', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({ data: [] })
      }),
      http.get('http://api.test/api/v1/admin/users/12', () => {
        return HttpResponse.json({
          id: 12,
          name: 'Customer User',
          email: 'customer@example.test',
          role: 'customer',
          is_active: true,
          agent_settings: null,
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/users/12/edit')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Edit User' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Assignment' })).not.toBeInTheDocument()

    // Switch role to agent
    await user.selectOptions(screen.getByLabelText('Role'), 'agent')

    expect(await screen.findByRole('heading', { name: 'Assignment' })).toBeInTheDocument()
  })
})
