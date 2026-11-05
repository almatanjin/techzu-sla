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

  it('shows SLA column and Breached badge for agent, but no SLA column for customer', async () => {
    const breachedTicket = {
      ticket_number: 'TKT-2026-000099',
      subject: 'Critical server outage',
      priority: 'critical',
      status: 'open',
      customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
      assigned_agent: null,
      category: { id: 1, name: 'Technical' },
      created_at: '2026-11-05T10:00:00Z',
      sla: {
        status: 'breached',
        started_at: '2026-11-05T10:00:00Z',
        warning_at: '2026-11-05T11:30:00Z',
        due_at: '2026-11-05T12:00:00Z',
        policy_minutes: 120,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [breachedTicket],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
            from: 1,
            to: 1,
          },
        })
      }),
    )

    // 1. Agent view
    signIn({ role: 'agent' })
    window.history.replaceState(null, '', '/tickets')
    const { unmount } = render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect((await screen.findAllByText('Critical server outage')).length).toBeGreaterThan(0)
    expect(screen.getByRole('columnheader', { name: 'SLA' })).toBeInTheDocument()
    expect(screen.getAllByText('Breached').length).toBe(3) // filter option + table + mobile card
    expect(screen.getAllByText(new Date('2026-11-05T12:00:00Z').toLocaleString()).length).toBeGreaterThanOrEqual(1)

    unmount()

    // 2. Customer view (no SLA data)
    const customerTicket = { ...breachedTicket }
    delete (customerTicket as Record<string, unknown>).sla

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [customerTicket],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
            from: 1,
            to: 1,
          },
        })
      }),
    )

    signIn({ role: 'customer' })
    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect((await screen.findAllByText('Critical server outage')).length).toBeGreaterThan(0)
    expect(screen.queryByRole('columnheader', { name: 'SLA' })).not.toBeInTheDocument()
    expect(screen.queryByText('Breached')).not.toBeInTheDocument()
  })


  it('filters tickets by sla_status for agent and hides sla filter for customer', async () => {
    const user = userEvent.setup()
    let capturedSlaStatus: string | null = null

    server.use(
      http.get('http://api.test/api/v1/tickets', ({ request }) => {
        const url = new URL(request.url)
        capturedSlaStatus = url.searchParams.get('sla_status')
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

    // 1. Agent opening with sla_status=breached
    signIn({ role: 'agent' })
    window.history.replaceState(null, '', '/tickets?sla_status=breached')
    const { unmount } = render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    const slaSelect = screen.getByLabelText('SLA Status') as HTMLSelectElement
    expect(slaSelect.value).toBe('breached')
    expect(capturedSlaStatus).toBe('breached')

    // Change to Approaching
    await user.selectOptions(slaSelect, 'approaching')
    expect(window.location.search).toContain('sla_status=approaching')
    expect(capturedSlaStatus).toBe('approaching')

    unmount()

    // 2. Customer sees no SLA filter
    signIn({ role: 'customer' })
    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect(screen.queryByLabelText('SLA Status')).not.toBeInTheDocument()
  })
})

