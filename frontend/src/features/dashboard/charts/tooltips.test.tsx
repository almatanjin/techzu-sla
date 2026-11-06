import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { agentRow, dailyFixture } from '../testFixtures'
import { AgentWorkloadTooltip } from './AgentWorkloadChart'
import { CreatedResolvedTooltip } from './CreatedResolvedChart'
import { SlaComplianceTooltip } from './SlaComplianceChart'
import { SlaHealthTooltip } from './SlaHealthChart'

const fifthOfNovember = (overrides: Parameters<typeof dailyFixture>[0]) =>
  dailyFixture(overrides).find((day) => day.date === '2026-11-05')!

describe('chart tooltips', () => {
  it('Created vs resolved shows the day and both counts', () => {
    render(<CreatedResolvedTooltip active payload={[{ payload: fifthOfNovember({ '2026-11-05': { created: 3, resolved: 2 } }) }]} />)

    expect(screen.getByText('Thu 5 Nov')).toBeInTheDocument()
    expect(screen.getByText('Created: 3')).toBeInTheDocument()
    expect(screen.getByText('Resolved: 2')).toBeInTheDocument()
  })

  it('Created vs resolved renders nothing when not active', () => {
    const { container } = render(<CreatedResolvedTooltip active={false} payload={[]} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('Daily SLA compliance shows the day, both counts and the met share', () => {
    render(<SlaComplianceTooltip active payload={[{ payload: fifthOfNovember({ '2026-11-05': { sla_met: 3, sla_missed: 1 } }) }]} />)

    expect(screen.getByText('Thu 5 Nov')).toBeInTheDocument()
    expect(screen.getByText('Met SLA: 3')).toBeInTheDocument()
    expect(screen.getByText('Missed SLA: 1')).toBeInTheDocument()
    expect(screen.getByText('75% met')).toBeInTheDocument()
  })

  it('Daily SLA compliance shows a day with nothing finished without a share', () => {
    render(<SlaComplianceTooltip active payload={[{ payload: fifthOfNovember({}) }]} />)

    expect(screen.getByText('No tickets finished')).toBeInTheDocument()
    expect(screen.queryByText(/% met/)).not.toBeInTheDocument()
  })

  it('SLA health shows the part, its count and its share', () => {
    render(<SlaHealthTooltip active payload={[{ payload: { key: 'breached', label: 'Breached', count: 2, share: '20%' } }]} />)

    expect(screen.getByText('Breached')).toBeInTheDocument()
    expect(screen.getByText('2 tickets (20%)')).toBeInTheDocument()
  })

  it("Workload by agent shows the agent's name and the three counts", () => {
    const mina = agentRow({ id: 10, name: 'Mina', open: 2, sla_within: 1, sla_breached: 1 })
    render(<AgentWorkloadTooltip active payload={[{ payload: mina }]} />)

    expect(screen.getByText('Mina')).toBeInTheDocument()
    expect(screen.getByText('Within SLA: 1')).toBeInTheDocument()
    expect(screen.getByText('Approaching: 0')).toBeInTheDocument()
    expect(screen.getByText('Breached: 1')).toBeInTheDocument()
  })

  it('Workload by agent labels a deactivated agent "(inactive)"', () => {
    const sam = agentRow({ id: 20, name: 'Sam', is_active: false, open: 1, sla_within: 1 })
    render(<AgentWorkloadTooltip active payload={[{ payload: sam }]} />)

    expect(screen.getByText('Sam (inactive)')).toBeInTheDocument()
  })
})
