import { DashboardPage } from '../features/dashboard/DashboardPage'
import { BackendStatus } from '../features/health/BackendStatus'

export function HomePage() {
  return (
    <div className="space-y-8">
      <DashboardPage />
      <div className="border-t border-border pt-4">
        <BackendStatus />
      </div>
    </div>
  )
}
