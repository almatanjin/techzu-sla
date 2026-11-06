import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'
import { agentRow, dailyFixture, staffDashboard } from './testFixtures'
import type { DashboardResponse } from './types'

function serveDashboard(body: DashboardResponse) {
  server.use(http.get('http://api.test/api/v1/dashboard', () => HttpResponse.json(body)))
}

async function sectionHeadings() {
  // The charts load lazily, so wait for the last staff chart before reading the order.
  await screen.findByRole('heading', { name: 'Tickets by priority' })
  return screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent)
}

describe('Dashboard charts', () => {
  beforeEach(() => {
    server.use(
      http.get('*/api/v1/health', () => HttpResponse.json({ status: 'ok', checks: { database: 'ok' } })),
    )
  })

  it("shows an admin's sections in order, with no My queue and the charts before the agent table", async () => {
    signIn({ role: 'admin' })
    serveDashboard(staffDashboard({ by_agent: [agentRow({ id: 10, name: 'Mina' })] }))

    render(<Root />)

    expect(await sectionHeadings()).toEqual([
      'Team overview',
      'Created vs resolved',
      'Daily SLA compliance',
      'SLA health',
      'Tickets by priority',
      'Workload by agent',
      'Tickets by agent',
    ])
  })

  it("shows an agent's sections in order, with no workload chart or agent table", async () => {
    signIn({ role: 'agent' })
    serveDashboard(staffDashboard())

    render(<Root />)

    expect(await sectionHeadings()).toEqual([
      'Team overview',
      'My queue',
      'Created vs resolved',
      'Daily SLA compliance',
      'SLA health',
      'Tickets by priority',
    ])
  })

  it('shows a customer no charts', async () => {
    signIn({ role: 'customer' })
    serveDashboard({
      generated_at: '2026-11-06T04:00:00Z',
      summary: { open: 1, waiting_for_customer: 0, closed: 0 },
      recent_tickets: [],
    })

    render(<Root />)

    await screen.findByRole('region', { name: 'Your ticket overview' })
    for (const name of ['Created vs resolved', 'Daily SLA compliance', 'SLA health', 'Workload by agent']) {
      expect(screen.queryByRole('heading', { name })).not.toBeInTheDocument()
    }
  })

  describe('Created vs resolved', () => {
    it('shows a legend naming both lines and a table with the 14 days', async () => {
      signIn({ role: 'agent' })
      serveDashboard(
        staffDashboard({
          daily: dailyFixture({
            '2026-11-05': { created: 3, resolved: 2 },
            '2026-11-06': { created: 1 },
          }),
        }),
      )

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Created vs resolved' })
      const legend = within(section).getByRole('list')
      expect(within(legend).getByText('Created')).toBeInTheDocument()
      expect(within(legend).getByText('Resolved')).toBeInTheDocument()

      await userEvent.click(within(section).getByRole('button', { name: 'Show table' }))

      const table = within(section).getByRole('table', { name: 'Created vs resolved' })
      expect(within(table).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual([
        'Date',
        'Created',
        'Resolved',
      ])
      const rows = within(table).getAllByRole('row').slice(1)
      expect(rows).toHaveLength(14)
      expect(rows[0]).toHaveTextContent('Sat 24 Oct')
      expect(rows[12].textContent).toBe('Thu 5 Nov32')
      expect(rows[13].textContent).toBe('Fri 6 Nov10')
      expect(within(section).getByRole('button', { name: 'Hide table' })).toBeInTheDocument()
    })

    it('shows the empty text when nothing was created or resolved', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ daily: dailyFixture({ '2026-11-05': { sla_missed: 1 } }) }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Created vs resolved' })
      expect(within(section).getByText('No tickets created or resolved in the last 14 days.')).toBeInTheDocument()
      expect(within(section).queryByRole('button', { name: 'Show table' })).not.toBeInTheDocument()
    })
  })

  describe('Daily SLA compliance', () => {
    it('shows a legend and a table with Met SLA and Missed SLA for the 14 days', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ daily: dailyFixture({ '2026-11-05': { resolved: 4, sla_met: 3, sla_missed: 1 } }) }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Daily SLA compliance' })
      const legend = within(section).getByRole('list')
      expect(within(legend).getByText('Met SLA')).toBeInTheDocument()
      expect(within(legend).getByText('Missed SLA')).toBeInTheDocument()

      await userEvent.click(within(section).getByRole('button', { name: 'Show table' }))

      const table = within(section).getByRole('table', { name: 'Daily SLA compliance' })
      expect(within(table).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual([
        'Date',
        'Met SLA',
        'Missed SLA',
      ])
      const rows = within(table).getAllByRole('row').slice(1)
      expect(rows).toHaveLength(14)
      expect(rows[12].textContent).toBe('Thu 5 Nov31')
      expect(within(section).getByRole('button', { name: 'Hide table' })).toBeInTheDocument()
    })

    it('shows the empty text when no ticket was finished', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ daily: dailyFixture({ '2026-11-05': { created: 2 } }) }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Daily SLA compliance' })
      expect(within(section).getByText('No tickets finished in the last 14 days.')).toBeInTheDocument()
    })
  })

  describe('SLA health', () => {
    it('shows the active total and a legend with counts and shares that link to the lists', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ team: { open: 10, sla_within: 6, sla_approaching: 2, sla_breached: 2 } }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'SLA health' })
      expect(within(section).getByText('10')).toBeInTheDocument()
      expect(within(section).getByText('active')).toBeInTheDocument()

      expect(within(section).getByRole('link', { name: 'Within SLA: 6 (60%)' })).toHaveAttribute(
        'href',
        '/tickets?sla_status=within',
      )
      expect(within(section).getByRole('link', { name: 'Approaching: 2 (20%)' })).toHaveAttribute(
        'href',
        '/tickets?sla_status=approaching',
      )
      expect(within(section).getByRole('link', { name: 'Breached: 2 (20%)' })).toHaveAttribute(
        'href',
        '/tickets?sla_status=breached',
      )
    })

    it('never shows a small part as 0% or a large part as 100%', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ team: { open: 1000, sla_within: 2, sla_approaching: 0, sla_breached: 998 } }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'SLA health' })
      expect(within(section).getByRole('link', { name: 'Within SLA: 2 (<1%)' })).toBeInTheDocument()
      expect(within(section).getByRole('link', { name: 'Approaching: 0 (0%)' })).toBeInTheDocument()
      expect(within(section).getByRole('link', { name: 'Breached: 998 (>99%)' })).toBeInTheDocument()
    })

    it('shows "No active tickets." when nothing is active', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard())

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'SLA health' })
      expect(within(section).getByText('No active tickets.')).toBeInTheDocument()
    })
  })

  describe('Workload by agent', () => {
    it('shows a legend naming the three parts for an admin', async () => {
      signIn({ role: 'admin' })
      serveDashboard(
        staffDashboard({
          team: { open: 2, sla_within: 1, sla_breached: 1 },
          by_agent: [
            agentRow({ id: 10, name: 'Mina', open: 2, sla_within: 1, sla_breached: 1 }),
            agentRow({ id: 20, name: 'Rafi' }),
          ],
        }),
      )

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Workload by agent' })
      const legend = within(section).getByRole('list')
      expect(within(legend).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
        'Within SLA',
        'Approaching',
        'Breached',
      ])
    })

    it('is not shown to an agent', async () => {
      signIn({ role: 'agent' })
      serveDashboard(staffDashboard({ team: { open: 1, sla_within: 1 } }))

      render(<Root />)

      await screen.findByRole('region', { name: 'SLA health' })
      expect(screen.queryByRole('region', { name: 'Workload by agent' })).not.toBeInTheDocument()
    })

    it('shows the empty text when no agent holds an active ticket', async () => {
      signIn({ role: 'admin' })
      serveDashboard(staffDashboard({ by_agent: [agentRow({ id: 10, name: 'Mina' }), agentRow({ id: 20, name: 'Rafi' })] }))

      render(<Root />)

      const section = await screen.findByRole('region', { name: 'Workload by agent' })
      expect(within(section).getByText('No active tickets are assigned.')).toBeInTheDocument()
    })
  })
})
