import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('TicketDetailPage', () => {
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
      http.get('http://api.test/api/v1/tickets/:ticketNumber/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('shows only "Reopen" and "Close" buttons and no edit form for a customer viewing their resolved ticket', async () => {
    signIn({ role: 'customer' })

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Resolved billing problem',
            description: 'My invoice was fixed',
            priority: 'low',
            status: 'resolved',
            customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust User' },
            assigned_agent: { id: 2, name: 'Support Agent' },
            category: { id: 1, name: 'Billing' },
            resolved_at: '2026-11-04T12:00:00Z',
            closed_at: null,
            lock_version: 2,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-04T12:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: ['open', 'closed'],
            },
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Resolved billing problem')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reopen Ticket' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument()

    // No edit form buttons or inputs
    expect(screen.queryByRole('button', { name: 'Save Changes' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Subject')).not.toBeInTheDocument()
  })

  it('allows an agent to save a priority change', async () => {
    signIn({ role: 'agent' })
    const user = userEvent.setup()
    let patchPayload: unknown = null

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000002', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000002',
            subject: 'System down',
            description: 'Production cluster down',
            priority: 'medium',
            status: 'in_progress',
            customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust User' },
            assigned_agent: { id: 2, name: 'Agent Smith' },
            category: { id: 2, name: 'Technical' },
            resolved_at: null,
            closed_at: null,
            lock_version: 1,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: true,
              allowed_statuses: ['waiting_for_customer', 'resolved'],
            },
          },
        })
      }),
      http.patch('http://api.test/api/v1/tickets/TKT-2026-000002', async ({ request }) => {
        patchPayload = await request.json()
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000002',
            subject: 'System down',
            description: 'Production cluster down',
            priority: 'critical',
            status: 'in_progress',
            customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust User' },
            assigned_agent: { id: 2, name: 'Agent Smith' },
            category: { id: 2, name: 'Technical' },
            resolved_at: null,
            closed_at: null,
            lock_version: 2,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:30:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: true,
              allowed_statuses: ['waiting_for_customer', 'resolved'],
            },
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000002')
    render(<Root />)

    expect(await screen.findByText('System down')).toBeInTheDocument()
    expect(await screen.findByLabelText('Priority')).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Priority'), 'critical')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(patchPayload).toEqual({
      category_id: 2,
      priority: 'critical',
      lock_version: 1,
    })
    expect(await screen.findByText('Critical')).toBeInTheDocument()
  })

  it('displays conflict message and latest values when receiving a 409 conflict', async () => {
    signIn({ role: 'agent' })
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000003', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000003',
            subject: 'Original subject',
            description: 'Original description',
            priority: 'low',
            status: 'open',
            customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust User' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 1,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: true,
              allowed_statuses: ['in_progress', 'closed'],
            },
          },
        })
      }),
      http.patch('http://api.test/api/v1/tickets/TKT-2026-000003', async () => {
        return HttpResponse.json(
          {
            message: 'This ticket was changed by someone else. The latest version is now shown.',
            ticket: {
              ticket_number: 'TKT-2026-000003',
              subject: 'Concurrent edit subject by other agent',
              description: 'Concurrent description',
              priority: 'high',
              status: 'open',
              customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
              created_by: { id: 1, name: 'Cust User' },
              assigned_agent: null,
              category: { id: 1, name: 'Billing' },
              resolved_at: null,
              closed_at: null,
              lock_version: 2,
              created_at: '2026-11-01T10:00:00Z',
              updated_at: '2026-11-01T10:15:00Z',
              permissions: {
                can_edit_content: true,
                can_edit_classification: true,
                allowed_statuses: ['in_progress', 'closed'],
              },
            },
          },
          { status: 409 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000003')
    render(<Root />)

    expect(await screen.findByDisplayValue('Original subject')).toBeInTheDocument()

    await user.clear(screen.getByLabelText('Subject'))
    await user.type(screen.getByLabelText('Subject'), 'My conflicting update')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(
      await screen.findByText('This ticket was changed by someone else. The latest version is now shown.'),
    ).toBeInTheDocument()

    // Latest version is now shown in form
    expect(await screen.findByDisplayValue('Concurrent edit subject by other agent')).toBeInTheDocument()
  })
})

