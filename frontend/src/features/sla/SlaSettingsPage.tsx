import { BusinessHoursSection } from './BusinessHoursSection'
import { HolidaysSection } from './HolidaysSection'
import { SlaPoliciesSection } from './SlaPoliciesSection'

export function SlaSettingsPage() {
  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-fg">SLA &amp; Business Hours</h1>
        <p className="mt-1 text-sm text-fg-muted">
          Manage support targets, weekly operating schedule, and organization holidays.
        </p>
      </div>

      <SlaPoliciesSection />
      <BusinessHoursSection />
      <HolidaysSection />
    </div>
  )
}

