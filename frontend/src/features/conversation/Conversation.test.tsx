import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('Conversation tracer', () => {
  beforeEach(() => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', () => {
        return HttpResponse.json({
          data: [{ id: 1, name: 'Billing', is_active: true }],
        })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('allows Ana to type a reply and click Send reply, displaying it in thread and timeline', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()

    let postedFormData: FormData | null = null
    let messages = [
      {
        id: 10,
        type: 'reply',
        body: 'Initial question',
        author: { id: 1, name: 'Ana', role: 'customer' },
        attachments: [],
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    const activities: Array<{
      id: number
      type: string
      actor: { id: number; name: string }
      old_values: null | Record<string, unknown>
      new_values: null | Record<string, unknown>
      created_at: string
    }> = [
      {
        id: 1,
        type: 'created',
        actor: { id: 1, name: 'Ana' },
        old_values: null,
        new_values: null,
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 0,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T10:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
              can_reply: true,
              can_add_internal_note: false,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [...messages].reverse(), // newest first from API
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({
          data: activities,
        })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000001/messages', async ({ request }) => {
        postedFormData = await request.formData()
        const newMsg = {
          id: 11,
          type: (postedFormData.get('type') as string) || 'reply',
          body: (postedFormData.get('body') as string) || '',
          author: { id: 1, name: 'Ana', role: 'customer' },
          attachments: [],
          created_at: '2026-11-05T10:05:00Z',
        }
        messages.push(newMsg)
        activities.push({
          id: 2,
          type: 'replied',
          actor: { id: 1, name: 'Ana' },
          old_values: null,
          new_values: { message_id: 11, attachment_count: 0 },
          created_at: '2026-11-05T10:05:00Z',
        })
        return HttpResponse.json({ data: newMsg }, { status: 201 })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()
    expect(await screen.findByText('Initial question')).toBeInTheDocument()

    const replyInput = screen.getByPlaceholderText(/reply/i)
    await user.type(replyInput, 'The invoice still shows the old address.')

    const sendButton = screen.getByRole('button', { name: 'Send reply' })
    await user.click(sendButton)

    // Verify reply appears in thread
    expect(await screen.findByText('The invoice still shows the old address.')).toBeInTheDocument()

    // Verify timeline updated
    expect(await screen.findByText('Replied')).toBeInTheDocument()

    // Verify sent form data
    expect(postedFormData).not.toBeNull()
    expect((postedFormData as unknown as FormData).get('body')).toBe('The invoice still shows the old address.')
    expect((postedFormData as unknown as FormData).get('type')).toBe('reply')
  })

  it('allows an agent to choose Internal note, type, click Add note, showing the note labelled Internal note and timeline Added an internal note', async () => {
    signIn({ role: 'agent', name: 'Rafi', id: 2 })
    const user = userEvent.setup()

    const messages = [
      {
        id: 10,
        type: 'reply',
        body: 'Initial question',
        author: { id: 1, name: 'Ana', role: 'customer' },
        attachments: [],
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    const activities: Array<{
      id: number
      type: string
      actor: { id: number; name: string }
      old_values: null | Record<string, unknown>
      new_values: null | Record<string, unknown>
      created_at: string
    }> = [
      {
        id: 1,
        type: 'created',
        actor: { id: 1, name: 'Ana' },
        old_values: null,
        new_values: null,
        created_at: '2026-11-05T10:00:00Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: { id: 2, name: 'Rafi', email: 'rafi@example.test' },
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 0,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T10:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: true,
              allowed_statuses: ['in_progress', 'waiting_for_customer', 'resolved', 'closed'],
              can_reply: true,
              can_add_internal_note: true,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [...messages].reverse(),
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({
          data: activities,
        })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000001/messages', async ({ request }) => {
        const formData = await request.formData()
        const newMsg = {
          id: 12,
          type: (formData.get('type') as string) || 'reply',
          body: (formData.get('body') as string) || '',
          author: { id: 2, name: 'Rafi', role: 'agent' },
          attachments: [],
          created_at: '2026-11-05T10:10:00Z',
        }
        messages.push(newMsg)
        activities.push({
          id: 2,
          type: 'internal_note_added',
          actor: { id: 2, name: 'Rafi' },
          old_values: null,
          new_values: { message_id: 12, attachment_count: 0 },
          created_at: '2026-11-05T10:10:00Z',
        })
        return HttpResponse.json({ data: newMsg }, { status: 201 })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    const noteRadio = screen.getByLabelText('Internal note')
    await user.click(noteRadio)

    const noteInput = screen.getByPlaceholderText(/internal note/i)
    await user.type(noteInput, 'Customer is on the legacy plan.')

    const submitBtn = screen.getByRole('button', { name: 'Add note' })
    await user.click(submitBtn)

    expect(await screen.findByText('Customer is on the legacy plan.')).toBeInTheDocument()
    expect(screen.getAllByText('Internal note').length).toBeGreaterThan(0)
    expect(await screen.findByText('Added an internal note')).toBeInTheDocument()
  })

  it('does not display the Internal note option to a customer', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 0,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T10:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
              can_reply: true,
              can_add_internal_note: false,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [],
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Send reply' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Internal note')).not.toBeInTheDocument()
  })

  it('shows closed message and no composer on a closed ticket', async () => {
    signIn({ role: 'agent', name: 'Rafi', id: 2 })

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'closed',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: { id: 2, name: 'Rafi', email: 'rafi@example.test' },
            category: { id: 1, name: 'Billing' },
            resolved_at: '2026-11-05T11:00:00Z',
            closed_at: '2026-11-05T12:00:00Z',
            lock_version: 3,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T12:00:00Z',
            permissions: {
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: [],
              can_reply: false,
              can_add_internal_note: false,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [],
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()
    expect(
      screen.getByText('This ticket is closed. New messages cannot be added.'),
    ).toBeInTheDocument()
    expect(screen.queryByPlaceholderText(/reply|note/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /send reply|add note/i })).not.toBeInTheDocument()
  })

  it('shows 30 messages and clicking Show earlier messages shows all 35 with oldest at top', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()

    const all35 = Array.from({ length: 35 }, (_, idx) => {
      const id = idx + 1
      return {
        id,
        type: 'reply',
        body: `Message number ${id}`,
        author: { id: 1, name: 'Ana', role: 'customer' },
        attachments: [],
        created_at: new Date(Date.UTC(2026, 10, 5, 10, id)).toISOString(),
      }
    })

    // API returns newest first (35 down to 6 on page 1, 5 down to 1 on page 2)
    const page1 = [...all35].reverse().slice(0, 30) // id 35 down to 6
    const page2 = [...all35].reverse().slice(30) // id 5 down to 1

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 0,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T10:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
              can_reply: true,
              can_add_internal_note: false,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', ({ request }) => {
        const url = new URL(request.url)
        const cursor = url.searchParams.get('cursor')
        if (!cursor) {
          return HttpResponse.json({
            data: page1,
            meta: { next_cursor: 'cursor_page_2' },
            links: { next: 'http://api.test/api/v1/tickets/TKT-2026-000001/messages?cursor=cursor_page_2' },
          })
        }
        return HttpResponse.json({
          data: page2,
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    // Page 1 is displayed: message 6 to 35
    expect(await screen.findByText('Message number 35')).toBeInTheDocument()
    expect(screen.getByText('Message number 6')).toBeInTheDocument()
    expect(screen.queryByText('Message number 1')).not.toBeInTheDocument()

    // Button to show earlier messages is visible
    const loadEarlierBtn = screen.getByRole('button', { name: 'Show earlier messages' })
    expect(loadEarlierBtn).toBeInTheDocument()

    await user.click(loadEarlierBtn)

    // Now message 1 is visible
    expect(await screen.findByText('Message number 1')).toBeInTheDocument()
    expect(screen.getByText('Message number 35')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Show earlier messages' })).not.toBeInTheDocument()

    // Oldest is at the top: verify DOM ordering
    const msg1 = screen.getByText('Message number 1')
    const msg35 = screen.getByText('Message number 35')
    expect(msg1.compareDocumentPosition(msg35) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('preserves typed text and displays API error message on a failed send', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001', () => {
        return HttpResponse.json({
          data: {
            ticket_number: 'TKT-2026-000001',
            subject: 'Invoice inquiry',
            description: 'Need help with my invoice',
            priority: 'medium',
            status: 'open',
            customer: { id: 1, name: 'Ana', email: 'ana@example.test' },
            created_by: { id: 1, name: 'Ana' },
            assigned_agent: null,
            category: { id: 1, name: 'Billing' },
            resolved_at: null,
            closed_at: null,
            lock_version: 0,
            created_at: '2026-11-05T10:00:00Z',
            updated_at: '2026-11-05T10:00:00Z',
            permissions: {
              can_edit_content: true,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
              can_reply: true,
              can_add_internal_note: false,
            },
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [],
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json(
          { message: 'Server temporarily unavailable.' },
          { status: 500 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    const input = screen.getByPlaceholderText(/reply/i)
    await user.type(input, 'Draft message that should remain.')

    const sendBtn = screen.getByRole('button', { name: 'Send reply' })
    await user.click(sendBtn)

    expect(await screen.findByRole('alert')).toHaveTextContent('Server temporarily unavailable.')
    expect(input).toHaveValue('Draft message that should remain.')
  })
})
