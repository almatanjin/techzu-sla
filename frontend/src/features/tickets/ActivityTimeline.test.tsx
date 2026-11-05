import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'
import { describeActivity } from './describeActivity'
import type { TicketActivity } from './types'

describe('describeActivity', () => {
  it('formats sentences for each activity type correctly', () => {
    const created: TicketActivity = {
      id: 1,
      ticket_id: 1,
      actor: { id: 1, name: 'Alice' },
      type: 'created',
      old_values: null,
      new_values: null,
      created_at: '2026-11-01T10:00:00Z',
    }
    expect(describeActivity(created)).toBe('Ticket created')

    const statusChanged: TicketActivity = {
      id: 2,
      ticket_id: 1,
      actor: { id: 2, name: 'Agent' },
      type: 'status_changed',
      old_values: { status: 'in_progress' },
      new_values: { status: 'resolved' },
      created_at: '2026-11-01T11:00:00Z',
    }
    expect(describeActivity(statusChanged)).toBe('Status changed from In Progress to Resolved')

    const priorityChanged: TicketActivity = {
      id: 3,
      ticket_id: 1,
      actor: { id: 2, name: 'Agent' },
      type: 'priority_changed',
      old_values: { priority: 'medium' },
      new_values: { priority: 'high' },
      created_at: '2026-11-01T11:15:00Z',
    }
    expect(describeActivity(priorityChanged)).toBe('Priority changed from Medium to High')

    const categoryChanged: TicketActivity = {
      id: 4,
      ticket_id: 1,
      actor: { id: 2, name: 'Agent' },
      type: 'category_changed',
      old_values: { category: { id: 1, name: 'Billing' } },
      new_values: { category: { id: 2, name: 'Technical' } },
      created_at: '2026-11-01T11:30:00Z',
    }
    expect(describeActivity(categoryChanged)).toBe('Category changed from Billing to Technical')

    const subjectChanged: TicketActivity = {
      id: 5,
      ticket_id: 1,
      actor: { id: 1, name: 'Alice' },
      type: 'subject_changed',
      old_values: { subject: 'Old Subject' },
      new_values: { subject: 'New Subject' },
      created_at: '2026-11-01T12:00:00Z',
    }
    expect(describeActivity(subjectChanged)).toBe('Subject changed from "Old Subject" to "New Subject"')

    const descriptionChanged: TicketActivity = {
      id: 6,
      ticket_id: 1,
      actor: { id: 1, name: 'Alice' },
      type: 'description_changed',
      old_values: { description: 'Old details' },
      new_values: { description: 'New details' },
      created_at: '2026-11-01T12:05:00Z',
    }
    expect(describeActivity(descriptionChanged)).toBe('Description updated')

    const claimed: TicketActivity = {
      id: 7,
      ticket_id: 1,
      actor: { id: 2, name: 'Agent' },
      type: 'claimed',
      old_values: null,
      new_values: { agent: { id: 2, name: 'Agent' } },
      created_at: '2026-11-01T12:10:00Z',
    }
    expect(describeActivity(claimed)).toBe('Claimed the ticket')

    const autoAssigned: TicketActivity = {
      id: 8,
      ticket_id: 1,
      actor: null,
      type: 'assigned',
      old_values: null,
      new_values: { agent: { id: 2, name: 'Rafi' } },
      created_at: '2026-11-01T12:15:00Z',
    }
    expect(describeActivity(autoAssigned)).toBe('Assigned automatically to Rafi')

    const assignedByAdmin: TicketActivity = {
      id: 9,
      ticket_id: 1,
      actor: { id: 99, name: 'Admin' },
      type: 'assigned',
      old_values: null,
      new_values: { agent: { id: 2, name: 'Rafi' } },
      created_at: '2026-11-01T12:20:00Z',
    }
    expect(describeActivity(assignedByAdmin)).toBe('Assigned to Rafi')

    const reassigned: TicketActivity = {
      id: 10,
      ticket_id: 1,
      actor: { id: 99, name: 'Admin' },
      type: 'assigned',
      old_values: { agent: { id: 2, name: 'Rafi' } },
      new_values: { agent: { id: 3, name: 'Mina' } },
      created_at: '2026-11-01T12:25:00Z',
    }
    expect(describeActivity(reassigned)).toBe('Reassigned from Rafi to Mina')

    const released: TicketActivity = {
      id: 11,
      ticket_id: 1,
      actor: { id: 2, name: 'Rafi' },
      type: 'unassigned',
      old_values: { agent: { id: 2, name: 'Rafi' } },
      new_values: null,
      created_at: '2026-11-01T12:30:00Z',
    }
    expect(describeActivity(released)).toBe('Released the ticket')

    const unassignedByAdmin: TicketActivity = {
      id: 12,
      ticket_id: 1,
      actor: { id: 99, name: 'Admin' },
      type: 'unassigned',
      old_values: { agent: { id: 2, name: 'Rafi' } },
      new_values: null,
      created_at: '2026-11-01T12:35:00Z',
    }
    expect(describeActivity(unassignedByAdmin)).toBe('Unassigned from Rafi')

    const unassignedAuto: TicketActivity = {
      id: 13,
      ticket_id: 1,
      actor: null,
      type: 'unassigned',
      old_values: { agent: { id: 2, name: 'Rafi' } },
      new_values: null,
      created_at: '2026-11-01T12:40:00Z',
    }
    expect(describeActivity(unassignedAuto)).toBe('Unassigned automatically from Rafi')

    const slaWarning: TicketActivity = {
      id: 14,
      ticket_id: 1,
      actor: null,
      type: 'sla_warning',
      old_values: null,
      new_values: { warning_at: '2026-11-01T12:30:00Z' },
      created_at: '2026-11-01T12:30:00Z',
    }
    expect(describeActivity(slaWarning)).toBe('SLA deadline approaching')

    const escalated: TicketActivity = {
      id: 15,
      ticket_id: 1,
      actor: null,
      type: 'escalated',
      old_values: null,
      new_values: { due_at: '2026-11-01T13:00:00Z' },
      created_at: '2026-11-01T13:01:00Z',
    }
    expect(describeActivity(escalated)).toBe('Escalated: SLA deadline passed')
  })
})

describe('ActivityTimeline Component', () => {
  beforeEach(() => {
    signIn({ role: 'agent' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({ data: [] })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('renders timeline with actor name and "System" for entries without an actor', async () => {
    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000010', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000010',
            subject: 'System event ticket',
            description: 'Automated check ticket',
            priority: 'low',
            status: 'open',
            customer: { id: 1, name: 'Cust One', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust One' },
            assigned_agent: null,
            category: { id: 1, name: 'General' },
            resolved_at: null,
            closed_at: null,
            lock_version: 1,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: ['in_progress'],
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000010/activities', () => {
        return HttpResponse.json({
          data: [
            {
              id: 1,
              ticket_id: 10,
              actor: { id: 1, name: 'Alice Customer' },
              type: 'created',
              old_values: null,
              new_values: null,
              created_at: '2026-11-01T10:00:00Z',
            },
            {
              id: 2,
              ticket_id: 10,
              actor: null, // System event
              type: 'priority_changed',
              old_values: { priority: 'low' },
              new_values: { priority: 'high' },
              created_at: '2026-11-01T10:05:00Z',
            },
          ],
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000010')
    render(<Root />)

    expect(await screen.findByText('Alice Customer')).toBeInTheDocument()
    expect(screen.getByText('Ticket created')).toBeInTheDocument()
    expect(screen.getByText('System')).toBeInTheDocument()
    expect(screen.getByText('Priority changed from Low to High')).toBeInTheDocument()
  })

  it('refreshes the timeline after a status change', async () => {
    const user = userEvent.setup()

    const activitiesList: TicketActivity[] = [
      {
        id: 1,
        ticket_id: 11,
        actor: { id: 1, name: 'Alice Customer' },
        type: 'created',
        old_values: null,
        new_values: null,
        created_at: '2026-11-01T10:00:00Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000011', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000011',
            subject: 'Reopen test ticket',
            description: 'Checking timeline refresh',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Cust One', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust One' },
            assigned_agent: null,
            category: { id: 1, name: 'General' },
            resolved_at: null,
            closed_at: null,
            lock_version: 1,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: ['in_progress'],
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000011/activities', () => {
        return HttpResponse.json({
          data: activitiesList,
        })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000011/status', async () => {
        activitiesList.push({
          id: 2,
          ticket_id: 11,
          actor: { id: 2, name: 'Support Agent' },
          type: 'status_changed',
          old_values: { status: 'open' },
          new_values: { status: 'in_progress' },
          created_at: '2026-11-01T10:10:00Z',
        })

        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000011',
            subject: 'Reopen test ticket',
            description: 'Checking timeline refresh',
            priority: 'medium',
            status: 'in_progress',
            customer: { id: 1, name: 'Cust One', email: 'cust@example.test' },
            created_by: { id: 1, name: 'Cust One' },
            assigned_agent: { id: 2, name: 'Support Agent' },
            category: { id: 1, name: 'General' },
            resolved_at: null,
            closed_at: null,
            lock_version: 2,
            created_at: '2026-11-01T10:00:00Z',
            updated_at: '2026-11-01T10:10:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: ['resolved'],
            },
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000011')
    render(<Root />)

    expect(await screen.findByText('Ticket created')).toBeInTheDocument()

    const startWorkBtn = screen.getByRole('button', { name: 'Start Work' })
    await user.click(startWorkBtn)

    expect(await screen.findByText('Status changed from Open to In Progress')).toBeInTheDocument()
    expect(screen.getByText('Support Agent')).toBeInTheDocument()
  })
})
