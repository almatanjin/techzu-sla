import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('Attachment display and download (Task 5.4)', () => {
  let originalCreateObjectURL: typeof window.URL.createObjectURL
  let originalRevokeObjectURL: typeof window.URL.revokeObjectURL

  beforeEach(() => {
    originalCreateObjectURL = window.URL.createObjectURL
    originalRevokeObjectURL = window.URL.revokeObjectURL
    window.URL.createObjectURL = vi.fn().mockReturnValue('blob:http://api.test/mock-object-url')
    window.URL.revokeObjectURL = vi.fn()

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
    window.URL.createObjectURL = originalCreateObjectURL
    window.URL.revokeObjectURL = originalRevokeObjectURL
    window.history.replaceState(null, '', '/')
  })

  it('shows steps.txt (2 KB) button for a message attachment and clicking downloads it as a blob', async () => {
    signIn({ role: 'customer', name: 'Ana', id: 1 })
    const user = userEvent.setup()

    let downloadRequested = false
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

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
            attachments: [],
          },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/messages', () => {
        return HttpResponse.json({
          data: [
            {
              id: 10,
              type: 'reply',
              body: 'Here are the steps to reproduce.',
              author: { id: 2, name: 'Rafi', role: 'agent' },
              attachments: [
                {
                  id: 42,
                  name: 'steps.txt',
                  mime_type: 'text/plain',
                  size_bytes: 2048,
                  created_at: '2026-11-05T10:05:00Z',
                },
              ],
              created_at: '2026-11-05T10:05:00Z',
            },
          ],
          meta: { next_cursor: null },
          links: { next: null },
        })
      }),
      http.get('http://api.test/api/v1/tickets/TKT-2026-000001/activities', () => {
        return HttpResponse.json({ data: [] })
      }),
      http.get('http://api.test/api/v1/attachments/42/download', () => {
        downloadRequested = true
        return new HttpResponse('step 1\nstep 2', {
          headers: {
            'Content-Type': 'text/plain',
            'Content-Disposition': 'attachment; filename=steps.txt',
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets/TKT-2026-000001')
    render(<Root />)

    expect(await screen.findByText('Invoice inquiry')).toBeInTheDocument()

    // Button shows "steps.txt (2 KB)"
    const downloadBtn = await screen.findByRole('button', { name: 'steps.txt (2 KB)' })
    expect(downloadBtn).toBeInTheDocument()

    await user.click(downloadBtn)

    expect(downloadRequested).toBe(true)
    expect(window.URL.createObjectURL).toHaveBeenCalled()
    expect(clickSpy).toHaveBeenCalled()

    clickSpy.mockRestore()
  })

  it('renders ticket-level attachments under the description', async () => {
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
              can_edit_content: false,
              can_edit_classification: false,
              allowed_statuses: ['closed'],
              can_reply: true,
              can_add_internal_note: false,
            },
            attachments: [
              {
                id: 101,
                name: 'invoice-scan.pdf',
                mime_type: 'application/pdf',
                size_bytes: 4096,
                created_at: '2026-11-05T10:00:00Z',
              },
            ],
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
    expect(screen.getByText('Description')).toBeInTheDocument()
    expect(screen.getByText('invoice-scan.pdf (4 KB)')).toBeInTheDocument()
  })
})

