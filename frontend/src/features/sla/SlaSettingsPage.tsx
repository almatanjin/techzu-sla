import { useAuth } from '../auth/useAuth'
import { BusinessHoursSection } from './BusinessHoursSection'
import { HolidaysSection } from './HolidaysSection'
import { SlaPoliciesSection } from './SlaPoliciesSection'

export function SlaSettingsPage() {
  const { user } = useAuth()
  const readOnly = user?.role !== 'admin'

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-fg">SLA &amp; Business Hours</h1>
        <p className="mt-1 text-sm text-fg-muted">
          {readOnly
            ? 'View support targets, weekly operating schedule, and organization holidays.'
            : 'Manage support targets, weekly operating schedule, and organization holidays.'}
        </p>
      </div>

      <SlaPoliciesSection readOnly={readOnly} />
      <BusinessHoursSection readOnly={readOnly} />
      <HolidaysSection readOnly={readOnly} />
    </div>
  )
}

