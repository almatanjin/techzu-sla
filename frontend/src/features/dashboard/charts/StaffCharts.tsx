import { AgentWorkloadTable } from '../AgentWorkloadTable'
import { PriorityBar } from '../PriorityBar'
import type { StaffDashboard } from '../types'
import { AgentWorkloadChart } from './AgentWorkloadChart'
import { CreatedResolvedChart } from './CreatedResolvedChart'
import { SlaComplianceChart } from './SlaComplianceChart'
import { SlaHealthChart } from './SlaHealthChart'

type Props = {
  data: StaffDashboard
}

/**
 * The staff dashboard below "My queue": charts, the priority bar and, for Admins,
 * the workload chart and table. Loaded lazily so customers never download the chart library.
 */
export default function StaffCharts({ data }: Props) {
  return (
    <>
      <div className="grid gap-6 lg:grid-cols-2">
        <CreatedResolvedChart daily={data.daily} />
        <SlaComplianceChart daily={data.daily} />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <SlaHealthChart team={data.team} />
        <div className="card min-w-0 p-4">
          <PriorityBar priorities={data.by_priority} />
        </div>
      </div>

      {data.by_agent ? (
        <>
          <AgentWorkloadChart agents={data.by_agent} />
          <AgentWorkloadTable agents={data.by_agent} />
        </>
      ) : null}
    </>
  )
}
