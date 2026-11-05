import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('DashboardPage', () => {
  beforeEach(() => {
    signIn({ role: 'agent' })
    server.use(
      http.get('*/api/v1/health', () => {
        return HttpResponse.json({ status: 'ok', checks: { database: 'ok' } })
      }),
    )
  })

  it('tracer: shows team tiles, SLA breached: 4 tile linking to /tickets?sla_status=breached, My queue, and Updated time', async () => {
    const generatedAt = '2026-11-05T10:42:00Z'
    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: generatedAt,
          team: {
            open: 10,
            unassigned: 2,
            critical: 1,
            sla_breached: 4,
            sla_approaching: 3,
            resolved_today: 5,
            resolved_last_7_days: 12,
          },
          my_queue: {
            open: 3,
            sla_approaching: 1,
            sla_breached: 0,
          },
          by_priority: [
            { priority: 'critical', count: 1 },
            { priority: 'high', count: 2 },
            { priority: 'medium', count: 4 },
            { priority: 'low', count: 3 },
          ],
        })
      }),
    )

    render(<Root />)

    const teamSection = await screen.findByRole('region', { name: 'Team overview' })

    const breachedTile = within(teamSection).getByRole('link', { name: 'SLA breached: 4' })
    expect(breachedTile).toHaveAttribute('href', '/tickets?sla_status=breached')

    expect(within(teamSection).getByRole('link', { name: 'Open: 10' })).toHaveAttribute('href', '/tickets?view=active')
    expect(within(teamSection).getByRole('link', { name: 'Unassigned: 2' })).toHaveAttribute('href', '/tickets?assigned=unassigned')
    expect(within(teamSection).getByRole('link', { name: 'Critical: 1' })).toHaveAttribute('href', '/tickets?priority=critical')
    expect(within(teamSection).getByRole('link', { name: 'SLA approaching: 3' })).toHaveAttribute('href', '/tickets?sla_status=approaching')

    expect(screen.getByRole('heading', { name: 'My queue' })).toBeInTheDocument()

    const localTime = new Date(generatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    expect(screen.getByText(new RegExp(`Updated ${localTime}`))).toBeInTheDocument()
  })

  it('renders priority bar with 50% segments and legend links for 2 Critical and 2 Low tickets', async () => {
    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          team: {
            open: 4,
            unassigned: 0,
            critical: 2,
            sla_breached: 0,
            sla_approaching: 0,
            resolved_today: 0,
            resolved_last_7_days: 0,
          },
          my_queue: { open: 0, sla_approaching: 0, sla_breached: 0 },
          by_priority: [
            { priority: 'critical', count: 2 },
            { priority: 'high', count: 0 },
            { priority: 'medium', count: 0 },
            { priority: 'low', count: 2 },
          ],
        })
      }),
    )

    render(<Root />)

    const prioritySection = await screen.findByRole('region', { name: 'Tickets by priority' })

    // 2 segments of 50%
    const criticalSegment = within(prioritySection).getByLabelText(/Critical: 2 \(50%\)/)
    expect(criticalSegment).toHaveStyle({ width: '50%' })

    const lowSegment = within(prioritySection).getByLabelText(/Low: 2 \(50%\)/)
    expect(lowSegment).toHaveStyle({ width: '50%' })

    // Four links in legend with counts 2, 0, 0, 2
    const criticalLink = within(prioritySection).getByRole('link', { name: 'Critical: 2' })
    expect(criticalLink).toHaveAttribute('href', '/tickets?priority=critical')

    const highLink = within(prioritySection).getByRole('link', { name: 'High: 0' })
    expect(highLink).toHaveAttribute('href', '/tickets?priority=high')

    const mediumLink = within(prioritySection).getByRole('link', { name: 'Medium: 0' })
    expect(mediumLink).toHaveAttribute('href', '/tickets?priority=medium')

    const lowLink = within(prioritySection).getByRole('link', { name: 'Low: 2' })
    expect(lowLink).toHaveAttribute('href', '/tickets?priority=low')
  })

  it('renders "No active tickets." when all priority counts are zero', async () => {
    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          team: {
            open: 0,
            unassigned: 0,
            critical: 0,
            sla_breached: 0,
            sla_approaching: 0,
            resolved_today: 0,
            resolved_last_7_days: 0,
          },
          my_queue: { open: 0, sla_approaching: 0, sla_breached: 0 },
          by_priority: [
            { priority: 'critical', count: 0 },
            { priority: 'high', count: 0 },
            { priority: 'medium', count: 0 },
            { priority: 'low', count: 0 },
          ],
        })
      }),
    )

    render(<Root />)

    expect(await screen.findByText('No active tickets.')).toBeInTheDocument()
  })

  it('shows Tickets by agent for Admin with inactive label and link, and hides table for Agent', async () => {
    signIn({ role: 'admin' })

    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          team: {
            open: 3,
            unassigned: 0,
            critical: 0,
            sla_breached: 1,
            sla_approaching: 0,
            resolved_today: 0,
            resolved_last_7_days: 0,
          },
          my_queue: { open: 0, sla_approaching: 0, sla_breached: 0 },
          by_priority: [
            { priority: 'critical', count: 0 },
            { priority: 'high', count: 0 },
            { priority: 'medium', count: 0 },
            { priority: 'low', count: 0 },
          ],
          by_agent: [
            { id: 10, name: 'Mina', is_active: true, open: 2, sla_approaching: 0, sla_breached: 1 },
            { id: 20, name: 'Sam', is_active: false, open: 1, sla_approaching: 0, sla_breached: 0 },
          ],
        })
      }),
    )

    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Tickets by agent' })).toBeInTheDocument()

    const minaLink = screen.getByRole('link', { name: 'Mina' })
    expect(minaLink).toHaveAttribute('href', '/tickets?assigned=10')

    const samLink = screen.getByRole('link', { name: 'Sam' })
    expect(samLink).toHaveAttribute('href', '/tickets?assigned=20')
    expect(screen.getByText('(inactive)')).toBeInTheDocument()
  })

  it('does not show Tickets by agent table for an Agent', async () => {
    signIn({ role: 'agent' })

    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          team: {
            open: 0,
            unassigned: 0,
            critical: 0,
            sla_breached: 0,
            sla_approaching: 0,
            resolved_today: 0,
            resolved_last_7_days: 0,
          },
          my_queue: { open: 0, sla_approaching: 0, sla_breached: 0 },
          by_priority: [
            { priority: 'critical', count: 0 },
            { priority: 'high', count: 0 },
            { priority: 'medium', count: 0 },
            { priority: 'low', count: 0 },
          ],
        })
      }),
    )

    render(<Root />)

    await screen.findByRole('heading', { name: 'Team overview' })
    expect(screen.queryByRole('heading', { name: 'Tickets by agent' })).not.toBeInTheDocument()
  })

  it('shows Customer summary tiles and recent tickets with no SLA text for customer', async () => {
    signIn({ role: 'customer' })

    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          summary: {
            open: 3,
            waiting_for_customer: 1,
            closed: 2,
          },
          recent_tickets: [
            {
              ticket_number: 'TKT-2026-000001',
              subject: 'Billing issue',
              status: 'open',
              updated_at: '2026-11-05T09:30:00Z',
            },
          ],
        })
      }),
    )

    render(<Root />)

    const summarySection = await screen.findByRole('region', { name: 'Your ticket overview' })

    const openTile = within(summarySection).getByRole('link', { name: 'Open: 3' })
    expect(openTile).toHaveAttribute('href', '/tickets?view=active')

    const waitingTile = within(summarySection).getByRole('link', { name: 'Waiting for your reply: 1' })
    expect(waitingTile).toHaveAttribute('href', '/tickets?status=waiting_for_customer')

    const closedTile = within(summarySection).getByRole('link', { name: 'Resolved & closed: 2' })
    expect(closedTile).toHaveAttribute('href', '/tickets?view=closed')

    const ticketLink = screen.getByRole('link', { name: /TKT-2026-000001/ })
    expect(ticketLink).toHaveAttribute('href', '/tickets/TKT-2026-000001')

    // No SLA or agent text in customer dashboard
    const customerDashboard = summarySection.closest('.space-y-8')!
    expect(within(customerDashboard as HTMLElement).queryByText(/SLA/i)).not.toBeInTheDocument()
    expect(within(customerDashboard as HTMLElement).queryByText(/breached/i)).not.toBeInTheDocument()
    expect(within(customerDashboard as HTMLElement).queryByText(/agent/i)).not.toBeInTheDocument()
  })

  it('shows error state with Retry button on load failure while keeping backend status', async () => {
    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        return HttpResponse.json({ message: 'Internal Server Error' }, { status: 500 })
      }),
    )

    render(<Root />)

    expect(await screen.findByText('Could not load the dashboard.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()

    // Backend status is still shown
    expect(screen.getByText('Connected')).toBeInTheDocument()
  })

  it('refetches dashboard data when Refresh button is clicked', async () => {
    let callCount = 0
    server.use(
      http.get('http://api.test/api/v1/dashboard', () => {
        callCount++
        return HttpResponse.json({
          generated_at: '2026-11-05T10:00:00Z',
          team: {
            open: callCount === 1 ? 5 : 8,
            unassigned: 0,
            critical: 0,
            sla_breached: 0,
            sla_approaching: 0,
            resolved_today: 0,
            resolved_last_7_days: 0,
          },
          my_queue: { open: 0, sla_approaching: 0, sla_breached: 0 },
          by_priority: [
            { priority: 'critical', count: 0 },
            { priority: 'high', count: 0 },
            { priority: 'medium', count: 0 },
            { priority: 'low', count: 0 },
          ],
        })
      }),
    )

    render(<Root />)

    expect(await screen.findByRole('link', { name: 'Open: 5' })).toBeInTheDocument()

    const refreshBtn = screen.getByRole('button', { name: 'Refresh' })
    await userEvent.click(refreshBtn)

    await waitFor(() => {
      expect(screen.getByRole('link', { name: 'Open: 8' })).toBeInTheDocument()
    })
  })
})
