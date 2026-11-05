import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('TicketsListPage', () => {
  beforeEach(() => {
    signIn({ role: 'agent' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', () =>
        HttpResponse.json([
          { id: 1, name: 'Billing', is_active: true },
          { id: 2, name: 'Technical', is_active: true },
        ]),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('selects the right tab and requests page 2 when URL has view=closed and page=2', async () => {
    let capturedView: string | null = null
    let capturedPage: string | null = null

    server.use(
      http.get('http://api.test/api/v1/tickets', ({ request }) => {
        const url = new URL(request.url)
        capturedView = url.searchParams.get('view')
        capturedPage = url.searchParams.get('page')

        return HttpResponse.json({
          data: [
            {
              ticket_number: 'TKT-2026-000042',
              subject: 'Closed server issue',
              priority: 'high',
              status: 'closed',
              customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
              assigned_agent: null,
              category: { id: 1, name: 'Technical' },
              created_at: '2026-11-01T10:00:00Z',
            },
          ],
          meta: {
            current_page: 2,
            last_page: 3,
            per_page: 20,
            total: 45,
            from: 21,
            to: 40,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets?view=closed&page=2')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect((await screen.findAllByText('Closed server issue')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('TKT-2026-000042').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Unassigned').length).toBeGreaterThan(0)

    expect(capturedView).toBe('closed')
    expect(capturedPage).toBe('2')

    const closedTab = screen.getByRole('button', { name: /Resolved & Closed/i })
    expect(closedTab).toHaveAttribute('data-active', 'true')
  })

  it('updates the URL when switching tabs', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: null,
            to: null,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const closedTab = screen.getByRole('button', { name: /Resolved & Closed/i })
    await user.click(closedTab)

    expect(window.location.search).toContain('view=closed')

    const activeTab = screen.getByRole('button', { name: /Active/i })
    await user.click(activeTab)

    expect(window.location.search).toContain('view=active')
  })

  it('filters tickets by status and updates the query parameters', async () => {
    const user = userEvent.setup()
    let capturedStatus: string | null = null

    server.use(
      http.get('http://api.test/api/v1/tickets', ({ request }) => {
        const url = new URL(request.url)
        capturedStatus = url.searchParams.get('status')
        return HttpResponse.json({
          data: [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: null,
            to: null,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const statusSelect = screen.getByLabelText('Status')
    await user.selectOptions(statusSelect, 'resolved')

    expect(window.location.search).toContain('status=resolved')
    expect(window.location.search).toContain('view=closed')
    expect(capturedStatus).toBe('resolved')
  })
})
