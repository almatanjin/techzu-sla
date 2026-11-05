import { render, screen, within } from '@testing-library/react'
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

  it('an admin opening /tickets?priority=high&sort=-priority&per_page=50 sees Priority High, Sort Priority: highest first, Rows per page 50, and API was called with them', async () => {
    signIn({ role: 'admin' })

    let capturedPriority: string | null = null
    let capturedSort: string | null = null
    let capturedPerPage: string | null = null

    server.use(
      http.get('http://api.test/api/v1/tickets', ({ request }) => {
        const url = new URL(request.url)
        capturedPriority = url.searchParams.get('priority')
        capturedSort = url.searchParams.get('sort')
        capturedPerPage = url.searchParams.get('per_page')

        return HttpResponse.json({
          data: [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 0,
            from: null,
            to: null,
          },
        })
      }),
      http.get('http://api.test/api/v1/admin/agents', () => HttpResponse.json([])),
    )

    window.history.replaceState(
      null,
      '',
      '/tickets?priority=high&sort=-priority&per_page=50',
    )
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const prioritySelect = screen.getByLabelText(/priority/i) as HTMLSelectElement
    expect(prioritySelect.value).toBe('high')

    const sortSelect = screen.getByLabelText(/sort/i) as HTMLSelectElement
    expect(sortSelect.value).toBe('-priority')

    const perPageSelect = (await screen.findByLabelText(/rows per page/i)) as HTMLSelectElement
    expect(perPageSelect.value).toBe('50')

    expect(capturedPriority).toBe('high')
    expect(capturedSort).toBe('-priority')
    expect(capturedPerPage).toBe('50')
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
    expect(screen.queryByText('Escalated')).not.toBeInTheDocument()
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

  it('shows Breached and Escalated for an escalated ticket and only Breached for a non-escalated breached ticket', async () => {
    signIn({ role: 'agent' })

    const escalatedTicket = {
      ticket_number: 'TKT-2026-000001',
      subject: 'Escalated ticket issue',
      priority: 'critical',
      status: 'open',
      customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
      assigned_agent: null,
      category: { id: 1, name: 'Technical' },
      created_at: '2026-11-01T10:00:00Z',
      sla: {
        status: 'breached',
        started_at: '2026-11-01T10:00:00Z',
        warning_at: '2026-11-01T11:30:00Z',
        due_at: '2026-11-01T12:00:00Z',
        policy_minutes: 120,
        escalated_at: '2026-11-01T12:01:00Z',
      },
    }

    const nonEscalatedTicket = {
      ticket_number: 'TKT-2026-000002',
      subject: 'Breached but not escalated issue',
      priority: 'high',
      status: 'open',
      customer: { id: 2, name: 'Bob Customer', email: 'bob@example.com' },
      assigned_agent: null,
      category: { id: 1, name: 'Technical' },
      created_at: '2026-11-01T10:00:00Z',
      sla: {
        status: 'breached',
        started_at: '2026-11-01T10:00:00Z',
        warning_at: '2026-11-01T13:00:00Z',
        due_at: '2026-11-01T14:00:00Z',
        policy_minutes: 240,
        escalated_at: null,
      },
    }

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [escalatedTicket, nonEscalatedTicket],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 2,
            from: 1,
            to: 2,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect((await screen.findAllByText('Escalated ticket issue')).length).toBeGreaterThan(0)

    const rows = screen.getAllByRole('row')
    const rowEscalated = rows.find((r) => r.textContent?.includes('TKT-2026-000001'))!
    expect(rowEscalated).toBeDefined()
    expect(within(rowEscalated).getByText('Breached')).toBeInTheDocument()
    expect(within(rowEscalated).getByText('Escalated')).toBeInTheDocument()

    const rowNonEscalated = rows.find((r) => r.textContent?.includes('TKT-2026-000002'))!
    expect(rowNonEscalated).toBeDefined()
    expect(within(rowNonEscalated).getByText('Breached')).toBeInTheDocument()
    expect(within(rowNonEscalated).queryByText('Escalated')).not.toBeInTheDocument()
  })

  it('picking Priority Critical on page 3 sets priority=critical and removes page', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [],
          meta: {
            current_page: 3,
            last_page: 5,
            per_page: 20,
            total: 100,
            from: 41,
            to: 60,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets?page=3')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const prioritySelect = screen.getByLabelText(/priority/i)
    await user.selectOptions(prioritySelect, 'critical')

    expect(window.location.search).toContain('priority=critical')
    expect(window.location.search).not.toContain('page=')
  })

  it('picking created from and to dates sets created_from and created_to, and the to input minimum is the from date', async () => {
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

    const fromInput = screen.getByLabelText(/created from/i) as HTMLInputElement
    const toInput = screen.getByLabelText(/created to/i) as HTMLInputElement

    await user.type(fromInput, '2026-11-01')
    await user.type(toInput, '2026-11-03')

    expect(window.location.search).toContain('created_from=2026-11-01')
    expect(window.location.search).toContain('created_to=2026-11-03')
    expect(toInput.min).toBe('2026-11-01')
  })

  it('Reset filters on the Resolved & Closed tab clears every filter and keeps view=closed', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets', () => {
        return HttpResponse.json({
          data: [],
          meta: {
            current_page: 2,
            last_page: 3,
            per_page: 50,
            total: 100,
            from: 51,
            to: 100,
          },
        })
      }),
    )

    window.history.replaceState(
      null,
      '',
      '/tickets?view=closed&priority=high&search=server&sort=-priority&per_page=50&page=2',
    )
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const resetButton = await screen.findByRole('button', { name: /reset filters/i })
    await user.click(resetButton)

    expect(window.location.search).toBe('?view=closed')
    const closedTab = screen.getByRole('button', { name: /Resolved & Closed/i })
    expect(closedTab).toHaveAttribute('data-active', 'true')
  })

  it('searching ab with a 422 response shows validation message under search box and keeps previous rows', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/tickets', ({ request }) => {
        const url = new URL(request.url)
        const search = url.searchParams.get('search')

        if (search === 'ab') {
          return HttpResponse.json(
            {
              message: 'The search field is invalid.',
              errors: {
                search: ['Search for words of at least 3 characters.'],
              },
            },
            { status: 422 },
          )
        }

        return HttpResponse.json({
          data: [
            {
              ticket_number: 'TKT-2026-000099',
              subject: 'Existing server outage ticket',
              priority: 'high',
              status: 'open',
              customer: { id: 1, name: 'Alice Customer', email: 'alice@example.com' },
              assigned_agent: null,
              category: { id: 1, name: 'Technical' },
              created_at: '2026-11-01T10:00:00Z',
            },
          ],
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

    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect((await screen.findAllByText('Existing server outage ticket')).length).toBeGreaterThan(0)

    const searchInput = screen.getByLabelText(/^search$/i)
    await user.type(searchInput, 'ab{Enter}')

    expect(
      await screen.findByText('Search for words of at least 3 characters.'),
    ).toBeInTheDocument()

    // Previous results are kept!
    expect(screen.getAllByText('Existing server outage ticket').length).toBeGreaterThan(0)
    // General error alert is NOT shown
    expect(screen.queryByText('Failed to load tickets.')).not.toBeInTheDocument()
  })

  it('an agent sees Assignment without colleague names and Sort offers SLA due: soonest first', async () => {
    signIn({ role: 'agent' })

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

    const assignedSelect = screen.getByLabelText(/assignment/i) as HTMLSelectElement
    const optionLabels = Array.from(assignedSelect.options).map((o) => o.text)
    expect(optionLabels).toEqual(['All', 'Assigned to me', 'Unassigned'])

    const sortSelect = screen.getByLabelText(/sort/i) as HTMLSelectElement
    const sortLabels = Array.from(sortSelect.options).map((o) => o.text)
    expect(sortLabels).toContain('SLA due: soonest first')
  })

  it('an admin sees active agents by name in Assignment and picking Rafi sets assigned to his id', async () => {
    const user = userEvent.setup()
    signIn({ role: 'admin' })

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
      http.get('http://api.test/api/v1/admin/agents', () => {
        return HttpResponse.json([
          { id: 11, name: 'Mina', is_available: true, active_ticket_count: 2, categories: [] },
          { id: 12, name: 'Rafi', is_available: true, active_ticket_count: 3, categories: [] },
        ])
      }),
    )

    window.history.replaceState(null, '', '/tickets')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const assignedSelect = (await screen.findByLabelText(/assignment/i)) as HTMLSelectElement
    expect(await screen.findByRole('option', { name: 'Mina' })).toBeInTheDocument()
    expect(await screen.findByRole('option', { name: 'Rafi' })).toBeInTheDocument()

    await user.selectOptions(assignedSelect, '12')

    expect(window.location.search).toContain('assigned=12')
  })

  it('a customer sees only permitted filter controls and no SLA sort option', async () => {
    signIn({ role: 'customer' })

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

    // Permitted controls
    expect(screen.getByLabelText(/^search$/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/status/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/priority/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/category/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/created from/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/created to/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/sort/i)).toBeInTheDocument()
    expect(await screen.findByLabelText(/rows per page/i)).toBeInTheDocument()

    // Forbidden controls
    expect(screen.queryByLabelText(/assignment/i)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/^customer$/i)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/sla status/i)).not.toBeInTheDocument()

    const sortSelect = screen.getByLabelText(/sort/i) as HTMLSelectElement
    const sortLabels = Array.from(sortSelect.options).map((o) => o.text)
    expect(sortLabels).not.toContain('SLA due: soonest first')
  })

  it('opening /tickets?customer_id=1 resolves customer through API and shows Ana', async () => {
    signIn({ role: 'agent' })

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
      http.get('http://api.test/api/v1/customers/1', () => {
        return HttpResponse.json({
          id: 1,
          name: 'Ana',
          email: 'ana@example.com',
        })
      }),
    )

    window.history.replaceState(null, '', '/tickets?customer_id=1')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()
    expect(await screen.findByText('Ana')).toBeInTheDocument()
  })

  it('opening with assigned=99 when 99 is not in active agents list shows Agent #99', async () => {
    signIn({ role: 'admin' })

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
      http.get('http://api.test/api/v1/admin/agents', () => {
        return HttpResponse.json([
          { id: 11, name: 'Mina', is_available: true, active_ticket_count: 2, categories: [] },
          { id: 12, name: 'Rafi', is_available: true, active_ticket_count: 3, categories: [] },
        ])
      }),
    )

    window.history.replaceState(null, '', '/tickets?assigned=99')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument()

    const assignedSelect = (await screen.findByLabelText(/assignment/i)) as HTMLSelectElement
    expect(await screen.findByRole('option', { name: 'Agent #99' })).toBeInTheDocument()
    expect(assignedSelect.value).toBe('99')
  })
})

