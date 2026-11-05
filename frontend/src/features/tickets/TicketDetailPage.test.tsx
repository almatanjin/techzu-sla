import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'
import type { TicketStatus } from './types'

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

  it('shows SLA panel with status, due time and policy duration for staff and hides it for customer', async () => {
    const approachingTicket = {
      ticket_number: 'TKT-2026-000010',
      subject: 'Critical DB outage',
      description: 'Outage details',
      priority: 'critical',
      status: 'open',
      customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
      created_by: { id: 1, name: 'Alice Customer' },
      assigned_agent: null,
      category: { id: 2, name: 'Technical' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T09:00:00Z',
      updated_at: '2026-11-05T09:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'],
      },
      sla: {
        status: 'approaching',
        started_at: '2026-11-05T09:00:00Z',
        warning_at: '2026-11-05T10:30:00Z',
        due_at: '2026-11-05T11:00:00Z',
        policy_minutes: 120,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000010', () => {
        return HttpResponse.json({ data: approachingTicket })
      }),
    )

    // 1. Agent views approaching ticket
    signIn({ role: 'agent' })
    window.history.replaceState(null, '', '/tickets/TKT-2026-000010')
    const { unmount } = render(<Root />)

    expect(await screen.findByText('Critical DB outage')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'SLA' })).toBeInTheDocument()
    expect(screen.getByText('Approaching')).toBeInTheDocument()
    expect(screen.getByText(new Date('2026-11-05T11:00:00Z').toLocaleString())).toBeInTheDocument()
    expect(screen.getByText('Critical: 2 business hours')).toBeInTheDocument()

    unmount()

    // 2. Customer views ticket without SLA
    signIn({ role: 'customer' })
    const customerTicket = { ...approachingTicket }
    delete (customerTicket as Record<string, unknown>).sla

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000010', () => {
        return HttpResponse.json({ data: customerTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000010')
    render(<Root />)

    expect(await screen.findByText('Critical DB outage')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'SLA' })).not.toBeInTheDocument()
    expect(screen.queryByText('Approaching')).not.toBeInTheDocument()
    expect(screen.queryByText('Escalated')).not.toBeInTheDocument()
  })

  it('shows Escalated badge, Escalated at local time, and timeline entry when agent views escalated ticket', async () => {
    signIn({ role: 'agent' })

    const escalatedTicket = {
      ticket_number: 'TKT-2026-000020',
      subject: 'Critical outage breached and escalated',
      description: 'System down',
      priority: 'critical',
      status: 'in_progress',
      customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
      created_by: { id: 1, name: 'Alice Customer' },
      assigned_agent: null,
      category: { id: 2, name: 'Technical' },
      resolved_at: null,
      closed_at: null,
      lock_version: 2,
      created_at: '2026-11-08T09:00:00Z',
      updated_at: '2026-11-08T11:01:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'],
      },
      sla: {
        status: 'breached',
        started_at: '2026-11-08T09:00:00Z',
        warning_at: '2026-11-08T10:30:00Z',
        due_at: '2026-11-08T11:00:00Z',
        policy_minutes: 120,
        escalated_at: '2026-11-08T11:01:00Z',
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000020', () => {
        return HttpResponse.json({ data: escalatedTicket })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000020/activities', () => {
        return HttpResponse.json({
          data: [
            {
              id: 1,
              ticket_id: 20,
              actor: null,
              type: 'escalated',
              old_values: null,
              new_values: { due_at: '2026-11-08T11:00:00Z' },
              created_at: '2026-11-08T11:01:00Z',
            },
          ],
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000020')
    render(<Root />)

    expect(await screen.findByText('Critical outage breached and escalated')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'SLA' })).toBeInTheDocument()
    expect(screen.getByText('Breached')).toBeInTheDocument()
    expect(screen.getByText('Escalated')).toBeInTheDocument()
    expect(screen.getByText('Escalated at')).toBeInTheDocument()
    expect(screen.getByText(new Date('2026-11-08T11:01:00Z').toLocaleString())).toBeInTheDocument()

    // Activity timeline assertions
    expect(await screen.findByText('Escalated: SLA deadline passed')).toBeInTheDocument()
    expect(screen.getByText('System')).toBeInTheDocument()
  })

  it('updates SLA due time in detail panel after priority change is saved', async () => {
    signIn({ role: 'agent' })
    const user = userEvent.setup()

    const initialTicket = {
      ticket_number: 'TKT-2026-000011',
      subject: 'Performance slowdown',
      description: 'Slowdown details',
      priority: 'high',
      status: 'open',
      customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
      created_by: { id: 1, name: 'Alice Customer' },
      assigned_agent: null,
      category: { id: 2, name: 'Technical' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T09:00:00Z',
      updated_at: '2026-11-05T09:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'],
      },
      sla: {
        status: 'within',
        started_at: '2026-11-05T09:00:00Z',
        warning_at: '2026-11-05T15:00:00Z',
        due_at: '2026-11-05T17:00:00Z',
        policy_minutes: 480,
      },
    }

    const updatedTicket = {
      ...initialTicket,
      priority: 'critical',
      lock_version: 2,
      updated_at: '2026-11-05T09:30:00Z',
      sla: {
        status: 'approaching',
        started_at: '2026-11-05T09:00:00Z',
        warning_at: '2026-11-05T10:30:00Z',
        due_at: '2026-11-05T11:00:00Z',
        policy_minutes: 120,
      },
    }

    let currentTicket = initialTicket

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000011', () => {
        return HttpResponse.json({ data: currentTicket })
      }),
      http.patch('http://api.test/api/v1/tickets/TKT-2026-000011', () => {
        currentTicket = updatedTicket
        return HttpResponse.json({ data: updatedTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000011')
    render(<Root />)

    expect(await screen.findByText('Performance slowdown')).toBeInTheDocument()
    expect(screen.getByText(new Date('2026-11-05T17:00:00Z').toLocaleString())).toBeInTheDocument()
    expect(screen.getByText('High: 8 business hours')).toBeInTheDocument()

    // Change priority to critical
    await user.selectOptions(screen.getByLabelText('Priority'), 'critical')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    // Panel updates with new SLA due time and policy duration
    expect(await screen.findByText('Critical: 2 business hours')).toBeInTheDocument()
    expect(screen.getByText(new Date('2026-11-05T11:00:00Z').toLocaleString())).toBeInTheDocument()
    expect(screen.getByText('Approaching')).toBeInTheDocument()
  })

  it('allows an agent on an unassigned ticket to claim it, showing agent as assignee and activity on timeline', async () => {
    signIn({ id: 2, name: 'Support Agent', role: 'agent' })
    const user = userEvent.setup()

    const unassignedTicket = {
      ticket_number: 'TKT-2026-000020',
      subject: 'Unassigned issue',
      description: 'Need help with setup',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: null as { id: number; name: string } | null,
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 0,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: false,
        can_claim: true,
        can_release: false,
      },
    }

    const claimedTicket = {
      ...unassignedTicket,
      assigned_agent: { id: 2, name: 'Support Agent' },
      lock_version: 1,
      permissions: {
        ...unassignedTicket.permissions,
        can_claim: false,
        can_release: true,
      },
    }

    const activities: Array<Record<string, unknown>> = [
      {
        id: 1,
        ticket_id: 20,
        actor: { id: 1, name: 'Cust User' },
        type: 'created',
        old_values: null,
        new_values: null,
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    let currentTicket: typeof unassignedTicket | typeof claimedTicket = unassignedTicket

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000020', () => {
        return HttpResponse.json({ data: currentTicket })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000020/activities', () => {
        return HttpResponse.json({ data: activities })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000020/claim', () => {
        currentTicket = claimedTicket
        activities.push({
          id: 2,
          ticket_id: 20,
          actor: { id: 2, name: 'Support Agent' },
          type: 'claimed',
          old_values: null,
          new_values: { agent: { id: 2, name: 'Support Agent' } },
          created_at: '2026-11-05T10:05:00Z',
        })
        return HttpResponse.json({ data: claimedTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000020')
    render(<Root />)

    expect(await screen.findByText('Unassigned issue')).toBeInTheDocument()
    const claimButton = screen.getByRole('button', { name: 'Claim' })
    await user.click(claimButton)

    const assignmentCard = screen.getByRole('heading', { name: 'Assignment' }).closest('.card') as HTMLElement
    expect(await within(assignmentCard).findByText('Support Agent')).toBeInTheDocument()
    expect(await screen.findByText('Claimed the ticket')).toBeInTheDocument()
  })

  it('displays conflict message and updates assignee when a 409 occurs on Claim', async () => {
    signIn({ id: 2, name: 'Support Agent', role: 'agent' })
    const user = userEvent.setup()

    const unassignedTicket = {
      ticket_number: 'TKT-2026-000021',
      subject: 'Contested ticket',
      description: 'Two agents want this ticket',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: null as { id: number; name: string } | null,
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 0,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: false,
        can_claim: true,
        can_release: false,
      },
    }

    const takenByMinaTicket = {
      ...unassignedTicket,
      assigned_agent: { id: 3, name: 'Mina' },
      lock_version: 1,
      permissions: {
        ...unassignedTicket.permissions,
        can_claim: false,
        can_release: false,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000021', () => {
        return HttpResponse.json({ data: unassignedTicket })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000021/claim', () => {
        return HttpResponse.json(
          {
            message: 'This ticket was already taken by Mina.',
            ticket: takenByMinaTicket,
          },
          { status: 409 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000021')
    render(<Root />)

    expect(await screen.findByText('Contested ticket')).toBeInTheDocument()
    const claimButton = screen.getByRole('button', { name: 'Claim' })
    await user.click(claimButton)

    expect(await screen.findByText('This ticket was already taken by Mina.')).toBeInTheDocument()
    const assignmentCard = screen.getByRole('heading', { name: 'Assignment' }).closest('.card') as HTMLElement
    expect(within(assignmentCard).getByText('Mina')).toBeInTheDocument()
    expect(within(assignmentCard).queryByRole('button', { name: 'Claim' })).not.toBeInTheDocument()
  })

  it('allows assignee to release ticket, showing Unassigned and Released the ticket on timeline', async () => {
    signIn({ id: 2, name: 'Rafi', role: 'agent' })
    const user = userEvent.setup()

    const assignedTicket = {
      ticket_number: 'TKT-2026-000022',
      subject: 'In progress ticket to release',
      description: 'Work started but need to release',
      priority: 'medium' as const,
      status: 'in_progress' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: { id: 2, name: 'Rafi' },
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['waiting_for_customer', 'resolved', 'closed'] as TicketStatus[],
        can_assign: false,
        can_claim: false,
        can_release: true,
      },
    }

    const releasedTicket = {
      ...assignedTicket,
      assigned_agent: null,
      lock_version: 2,
      permissions: {
        ...assignedTicket.permissions,
        can_claim: true,
        can_release: false,
      },
    }

    const activities: Array<Record<string, unknown>> = [
      {
        id: 1,
        ticket_id: 22,
        actor: { id: 2, name: 'Rafi' },
        type: 'claimed',
        old_values: null,
        new_values: { agent: { id: 2, name: 'Rafi' } },
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    let currentTicket: typeof assignedTicket | typeof releasedTicket = assignedTicket

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000022', () => {
        return HttpResponse.json({ data: currentTicket })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000022/activities', () => {
        return HttpResponse.json({ data: activities })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000022/release', () => {
        currentTicket = releasedTicket
        activities.push({
          id: 2,
          ticket_id: 22,
          actor: { id: 2, name: 'Rafi' },
          type: 'unassigned',
          old_values: { agent: { id: 2, name: 'Rafi' } },
          new_values: null,
          created_at: '2026-11-05T10:15:00Z',
        })
        return HttpResponse.json({ data: releasedTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000022')
    render(<Root />)

    expect(await screen.findByText('In progress ticket to release')).toBeInTheDocument()
    const releaseButton = screen.getByRole('button', { name: 'Release' })
    await user.click(releaseButton)

    const assignmentCard = screen.getByRole('heading', { name: 'Assignment' }).closest('.card') as HTMLElement
    expect(await within(assignmentCard).findByText('Unassigned')).toBeInTheDocument()
    expect(await screen.findByText('Released the ticket')).toBeInTheDocument()
    expect(within(assignmentCard).getByRole('button', { name: 'Claim' })).toBeInTheDocument()
  })

  it('shows assignee name to a customer viewing ticket but no Claim or Release buttons', async () => {
    signIn({ id: 1, name: 'Cust User', role: 'customer' })

    const assignedTicket = {
      ticket_number: 'TKT-2026-000023',
      subject: 'Customer viewing ticket',
      description: 'Customer sees who handles ticket',
      priority: 'low' as const,
      status: 'in_progress' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: { id: 2, name: 'Support Agent' },
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: false,
        allowed_statuses: ['closed'] as TicketStatus[],
        can_assign: false,
        can_claim: false,
        can_release: false,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000023', () => {
        return HttpResponse.json({ data: assignedTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000023')
    render(<Root />)

    expect(await screen.findByText('Customer viewing ticket')).toBeInTheDocument()
    const assignmentCard = screen.getByRole('heading', { name: 'Assignment' }).closest('.card') as HTMLElement
    expect(within(assignmentCard).getByText('Support Agent')).toBeInTheDocument()
    expect(within(assignmentCard).queryByRole('button', { name: 'Claim' })).not.toBeInTheDocument()
    expect(within(assignmentCard).queryByRole('button', { name: 'Release' })).not.toBeInTheDocument()
  })

  it('displays timeline entry with no actor as "Assigned automatically to Rafi" by "System"', async () => {
    signIn({ id: 2, name: 'Agent Viewer', role: 'agent' })

    const ticket = {
      ticket_number: 'TKT-2026-000024',
      subject: 'Auto assigned ticket',
      description: 'Created by customer, auto assigned by system',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: { id: 3, name: 'Rafi' },
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: false,
        can_claim: false,
        can_release: false,
      },
    }

    const activities = [
      {
        id: 1,
        ticket_id: 24,
        actor: { id: 1, name: 'Cust User' },
        type: 'created',
        old_values: null,
        new_values: null,
        created_at: '2026-11-05T10:00:00Z',
      },
      {
        id: 2,
        ticket_id: 24,
        actor: null,
        type: 'assigned',
        old_values: null,
        new_values: { agent: { id: 3, name: 'Rafi' } },
        created_at: '2026-11-05T10:00:01Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000024', () => {
        return HttpResponse.json({ data: ticket })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000024/activities', () => {
        return HttpResponse.json({ data: activities })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000024')
    render(<Root />)

    expect(await screen.findByText('Auto assigned ticket')).toBeInTheDocument()
    expect(await screen.findByText('Assigned automatically to Rafi')).toBeInTheDocument()
    expect(screen.getByText('System')).toBeInTheDocument()
  })

  it('allows an admin to reassign ticket to another agent using AgentPicker, showing options with availability and active count', async () => {
    signIn({ id: 99, name: 'Admin User', role: 'admin' })
    const user = userEvent.setup()

    const initialTicket = {
      ticket_number: 'TKT-2026-000025',
      subject: 'Ticket to reassign',
      description: 'Admin wants to move this ticket',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: { id: 2, name: 'Rafi' },
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: true,
        can_claim: false,
        can_release: false,
      },
    }

    const reassignedTicket = {
      ...initialTicket,
      assigned_agent: { id: 3, name: 'Mina' },
      lock_version: 2,
    }

    const agents = [
      {
        id: 2,
        name: 'Rafi',
        is_available: true,
        max_open_tickets: 10,
        categories: [{ id: 1, name: 'Billing' }],
        active_ticket_count: 3,
      },
      {
        id: 3,
        name: 'Mina',
        is_available: true,
        max_open_tickets: 5,
        categories: [{ id: 1, name: 'Billing' }],
        active_ticket_count: 1,
      },
      {
        id: 4,
        name: 'Kazi',
        is_available: false,
        max_open_tickets: null,
        categories: [],
        active_ticket_count: 0,
      },
    ]

    const activities: Array<Record<string, unknown>> = [
      {
        id: 1,
        ticket_id: 25,
        actor: { id: 99, name: 'Admin User' },
        type: 'assigned',
        old_values: null,
        new_values: { agent: { id: 2, name: 'Rafi' } },
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    let putPayload: unknown = null
    let currentTicket: typeof initialTicket | typeof reassignedTicket = initialTicket

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000025', () => {
        return HttpResponse.json({ data: currentTicket })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000025/activities', () => {
        return HttpResponse.json({ data: activities })
      }),
      http.get('http://api.test/api/v1/admin/agents', () => {
        return HttpResponse.json({ data: agents })
      }),
      http.put('http://api.test/api/v1/tickets/TKT-2026-000025/assignment', async ({ request }) => {
        putPayload = await request.json()
        currentTicket = reassignedTicket
        activities.push({
          id: 2,
          ticket_id: 25,
          actor: { id: 99, name: 'Admin User' },
          type: 'assigned',
          old_values: { agent: { id: 2, name: 'Rafi' } },
          new_values: { agent: { id: 3, name: 'Mina' } },
          created_at: '2026-11-05T10:10:00Z',
        })
        return HttpResponse.json({ data: reassignedTicket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000025')
    render(<Root />)

    expect(await screen.findByText('Ticket to reassign')).toBeInTheDocument()

    // AgentPicker select contains agents with available / unavailable and active count
    const select = await screen.findByRole('combobox', { name: /assign agent/i })
    expect(screen.getByRole('option', { name: /Rafi \(Available, 3 active\)/i })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Mina \(Available, 1 active\)/i })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Kazi \(Unavailable, 0 active\)/i })).toBeInTheDocument()

    // Select Mina and click Assign
    await user.selectOptions(select, '3')
    await user.click(screen.getByRole('button', { name: 'Assign' }))

    expect(putPayload).toEqual({
      agent_id: 3,
      lock_version: 1,
    })

    const assignmentCard = screen.getByRole('heading', { name: 'Assignment' }).closest('.card') as HTMLElement
    expect(within(assignmentCard).getByText('Mina')).toBeInTheDocument()
    expect(await screen.findByText('Reassigned from Rafi to Mina')).toBeInTheDocument()
  })

  it('allows an admin to unassign ticket sending agent_id null, and displays 409 conflict message on stale lock_version', async () => {
    signIn({ id: 99, name: 'Admin User', role: 'admin' })
    const user = userEvent.setup()

    const ticket = {
      ticket_number: 'TKT-2026-000026',
      subject: 'Ticket to unassign',
      description: 'Admin unassigns this ticket',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: { id: 2, name: 'Rafi' },
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 1,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: true,
        can_claim: false,
        can_release: false,
      },
    }

    let putPayload: unknown = null

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000026', () => {
        return HttpResponse.json({ data: ticket })
      }),
      http.get('http://api.test/api/v1/admin/agents', () => {
        return HttpResponse.json({
          data: [
            {
              id: 3,
              name: 'Mina',
              is_available: true,
              max_open_tickets: 5,
              categories: [],
              active_ticket_count: 1,
            },
          ],
        })
      }),
      http.put('http://api.test/api/v1/tickets/TKT-2026-000026/assignment', async ({ request }) => {
        putPayload = await request.json()
        return HttpResponse.json({
          data: {
            ...ticket,
            assigned_agent: null,
            lock_version: 2,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000026')
    render(<Root />)

    expect(await screen.findByText('Ticket to unassign')).toBeInTheDocument()
    const unassignBtn = await screen.findByRole('button', { name: 'Unassign' })
    await user.click(unassignBtn)

    expect(putPayload).toEqual({
      agent_id: null,
      lock_version: 1,
    })

    // Now test 409 conflict on assign
    server.use(
      http.put('http://api.test/api/v1/tickets/TKT-2026-000026/assignment', () => {
        return HttpResponse.json(
          {
            message: 'This ticket was changed by someone else. The latest version is now shown.',
            ticket: {
              ...ticket,
              assigned_agent: { id: 3, name: 'Mina' },
              lock_version: 3,
            },
          },
          { status: 409 },
        )
      }),
    )

    const select = await screen.findByRole('combobox', { name: /assign agent/i })
    await user.selectOptions(select, '3')
    await user.click(screen.getByRole('button', { name: 'Assign' }))

    expect(
      await screen.findByText('This ticket was changed by someone else. The latest version is now shown.'),
    ).toBeInTheDocument()
  })

  it('hides AgentPicker for agents even on unassigned tickets', async () => {
    signIn({ id: 2, name: 'Support Agent', role: 'agent' })

    const ticket = {
      ticket_number: 'TKT-2026-000027',
      subject: 'No picker for agent',
      description: 'Agent only sees claim',
      priority: 'medium' as const,
      status: 'open' as const,
      customer: { id: 1, name: 'Cust User', email: 'cust@example.test' },
      created_by: { id: 1, name: 'Cust User' },
      assigned_agent: null,
      category: { id: 1, name: 'Billing' },
      resolved_at: null,
      closed_at: null,
      lock_version: 0,
      created_at: '2026-11-05T10:00:00Z',
      updated_at: '2026-11-05T10:00:00Z',
      permissions: {
        can_edit_content: false,
        can_edit_classification: true,
        allowed_statuses: ['in_progress', 'closed'] as TicketStatus[],
        can_assign: false,
        can_claim: true,
        can_release: false,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000027', () => {
        return HttpResponse.json({ data: ticket })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000027')
    render(<Root />)

    expect(await screen.findByText('No picker for agent')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: /assign agent/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Assign' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Unassign' })).not.toBeInTheDocument()
  })
})

