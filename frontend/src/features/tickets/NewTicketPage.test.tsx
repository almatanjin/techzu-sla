import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('NewTicketPage', () => {
  beforeEach(() => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({
          data: [
            { id: 1, name: 'Billing', is_active: true },
            { id: 2, name: 'Technical', is_active: true },
          ],
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000099', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000099',
            subject: 'Cannot pay invoice',
            description: 'Credit card failed with code 4002',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Customer One', email: 'cust1@example.com' },
            created_by: { id: 1, name: 'Customer One' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 1,
            created_at: '2026-11-05T12:00:00Z',
            updated_at: '2026-11-05T12:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000099/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it("opens the new ticket's detail page when a customer submits successfully", async () => {
    signIn({ role: 'customer' })
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/tickets', async ({ request }) => {
        const body = (await request.json()) as { subject: string; description: string; category_id: number }
        return HttpResponse.json(
          {
            data: {
              ticket_number: 'TKT-2026-000099',
              subject: body.subject,
              description: body.description,
              priority: 'medium',
              status: 'open',
              customer: { id: 1, name: 'Customer One', email: 'cust1@example.com' },
              created_by: { id: 1, name: 'Customer One' },
              assigned_agent: null,
              category: { id: body.category_id, name: 'Billing' },
              resolved_at: null,
              closed_at: null,
              lock_version: 1,
              created_at: '2026-11-05T12:00:00Z',
              updated_at: '2026-11-05T12:00:00Z',
              permissions: {
                can_edit_content: true,
                can_edit_classification: false,
                allowed_statuses: ['closed'],
              },
            },
          },
          { status: 201 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/new')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'New Ticket' })).toBeInTheDocument()
    expect(await screen.findByRole('option', { name: 'Billing' })).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Category'), '1')
    await user.type(screen.getByLabelText('Subject'), 'Cannot pay invoice')
    await user.type(screen.getByLabelText('Description'), 'Credit card failed with code 4002')
    await user.click(screen.getByRole('button', { name: 'Submit Ticket' }))

    expect(await screen.findByText('TKT-2026-000099')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/tickets/TKT-2026-000099')
  })

  it('requires staff to pick a customer and shows error when not selected', async () => {
    signIn({ role: 'agent' })
    const user = userEvent.setup()

    window.history.replaceState(null, '', '/tickets/new')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'New Ticket' })).toBeInTheDocument()
    expect(screen.getByText('Customer')).toBeInTheDocument()
    expect(await screen.findByRole('option', { name: 'Billing' })).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Category'), '1')
    await user.type(screen.getByLabelText('Subject'), 'Staff opened ticket')
    await user.type(screen.getByLabelText('Description'), 'Some problem description')
    await user.click(screen.getByRole('button', { name: 'Submit Ticket' }))

    expect(await screen.findByText(/Please select a customer/i)).toBeInTheDocument()
  })

  it('displays API validation errors under the form fields', async () => {
    signIn({ role: 'customer' })
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/tickets', async () => {
        return HttpResponse.json(
          {
            message: 'The given data was invalid.',
            errors: {
              subject: ['The subject may not be greater than 200 characters.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/new')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'New Ticket' })).toBeInTheDocument()
    expect(await screen.findByRole('option', { name: 'Billing' })).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Category'), '1')
    await user.type(screen.getByLabelText('Subject'), 'Too long subject')
    await user.type(screen.getByLabelText('Description'), 'Valid description text')
    await user.click(screen.getByRole('button', { name: 'Submit Ticket' }))

    expect(
      await screen.findByText('The subject may not be greater than 200 characters.'),
    ).toBeInTheDocument()
  })
})
