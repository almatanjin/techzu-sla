import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'
import * as conversationApi from './api'

describe('File picker in composer (Task 5.3)', () => {
  beforeEach(() => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', () =>
        HttpResponse.json({
          data: [{ id: 1, name: 'Billing', is_active: true }],
        }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('choosing a.png and b.pdf lists both with sizes, removing a.png and sending uploads only b.pdf', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()
    const postMessageSpy = vi.spyOn(conversationApi, 'postMessage')

    const messages: Array<{
      id: number
      type: string
      body: string
      author: { id: number; name: string; role: string }
      attachments: Array<{
        id: number
        name: string
        mime_type: string
        size_bytes: number
        created_at: string
      }>
      created_at: string
    }> = []

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
          data: [...messages].reverse(),
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
      http.post('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        const newMsg = {
          id: 99,
          type: 'reply',
          body: 'Here is the pdf',
          author: { id: 1, name: 'Ana', role: 'customer' },
          attachments: [
            {
              id: 1,
              name: 'b.pdf',
              mime_type: 'application/pdf',
              size_bytes: 1024,
              created_at: '2026-11-05T10:05:00Z',
            },
          ],
          created_at: '2026-11-05T10:05:00Z',
        }
        messages.push(newMsg)
        return HttpResponse.json({ data: newMsg }, { status: 201 })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    const fileA = new File(['a'.repeat(2048)], 'a.png', { type: 'image/png' })
    const fileB = new File(['b'.repeat(1024)], 'b.pdf', { type: 'application/pdf' })

    const fileInput = screen.getByTestId('file-picker-input')
    await user.upload(fileInput, [fileA, fileB])

    // Verify both are listed with sizes
    expect(screen.getByText('a.png (2 KB)')).toBeInTheDocument()
    expect(screen.getByText('b.pdf (1 KB)')).toBeInTheDocument()

    // Remove a.png
    const removeA = screen.getByLabelText('Remove a.png')
    await user.click(removeA)

    expect(screen.queryByText('a.png (2 KB)')).not.toBeInTheDocument()
    expect(screen.getByText('b.pdf (1 KB)')).toBeInTheDocument()

    // Type text and send
    const replyInput = screen.getByPlaceholderText(/reply/i)
    await user.type(replyInput, 'Here is the pdf')

    const sendBtn = screen.getByRole('button', { name: 'Send reply' })
    await user.click(sendBtn)

    expect(await screen.findByText('Here is the pdf')).toBeInTheDocument()

    // Verify upload
    expect(postMessageSpy).toHaveBeenCalled()
    const callArgs = postMessageSpy.mock.calls[0]
    expect(callArgs[1]).toBe('TKT-2026-000001')
    expect(callArgs[2].type).toBe('reply')
    expect(callArgs[2].body).toBe('Here is the pdf')
    expect(callArgs[2].files).toHaveLength(1)
    expect(callArgs[2].files?.[0].name).toBe('b.pdf')
  })

  it('choosing 6 files shows "You can attach up to 5 files." and sends nothing', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()
    const postMessageSpy = vi.spyOn(conversationApi, 'postMessage')

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

    const files = Array.from({ length: 6 }, (_, i) => {
      return new File(['content'], `file${i + 1}.png`, { type: 'image/png' })
    })

    const fileInput = screen.getByTestId('file-picker-input')
    await user.upload(fileInput, files)

    // Error message appears
    expect(screen.getByText('You can attach up to 5 files.')).toBeInTheDocument()

    // Type text and attempt to click Send reply
    const replyInput = screen.getByPlaceholderText(/reply/i)
    await user.type(replyInput, 'My message with too many files')

    const sendBtn = screen.getByRole('button', { name: 'Send reply' })
    expect(sendBtn).toBeDisabled()

    await user.click(sendBtn)
    expect(postMessageSpy).not.toHaveBeenCalled()
  })

  it('choosing setup.exe or an 11 MB file shows matching spec message and sends nothing', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()
    const postMessageSpy = vi.spyOn(conversationApi, 'postMessage')

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

    const badFile = new File(['binary'], 'setup.exe', { type: 'application/octet-stream' })
    const fileInput = screen.getByTestId('file-picker-input')
    await user.upload(fileInput, [badFile])

    expect(
      screen.getByText(
        'setup.exe must be one of these types: pdf, png, jpg, jpeg, gif, webp, txt, csv, log, docx, xlsx, zip.',
      ),
    ).toBeInTheDocument()

    const replyInput = screen.getByPlaceholderText(/reply/i)
    await user.type(replyInput, 'Trying to send exe')

    const sendBtn = screen.getByRole('button', { name: 'Send reply' })
    expect(sendBtn).toBeDisabled()
    await user.click(sendBtn)
    expect(postMessageSpy).not.toHaveBeenCalled()

    // Remove setup.exe
    const removeExe = screen.getByLabelText('Remove setup.exe')
    await user.click(removeExe)

    // Now upload 11 MB file
    const hugeFile = new File(['a'.repeat(11 * 1024 * 1024)], 'huge.pdf', {
      type: 'application/pdf',
    })
    await user.upload(fileInput, [hugeFile])

    expect(screen.getByText('huge.pdf must not be larger than 10 MB.')).toBeInTheDocument()
    expect(sendBtn).toBeDisabled()
    await user.click(sendBtn)
    expect(postMessageSpy).not.toHaveBeenCalled()
  })

  it('shows 422 on attachments.0 message beside picker and preserves typed text', async () => {
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
          {
            message: 'fake.pdf is not a valid PDF file.',
            errors: {
              'attachments.0': ['fake.pdf is not a valid PDF file.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    const replyInput = screen.getByPlaceholderText(/reply/i)
    await user.type(replyInput, 'Important note text')

    const fileInput = screen.getByTestId('file-picker-input')
    const file = new File(['not a real pdf'], 'fake.pdf', { type: 'application/pdf' })
    await user.upload(fileInput, [file])

    const sendBtn = screen.getByRole('button', { name: 'Send reply' })
    await user.click(sendBtn)

    // Verify error is shown beside file picker
    expect(await screen.findByText('fake.pdf is not a valid PDF file.')).toBeInTheDocument()

    // Verify typed text is preserved
    expect(replyInput).toHaveValue('Important note text')
  })
})
