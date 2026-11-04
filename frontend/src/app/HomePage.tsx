import { BackendStatus } from '../features/health/BackendStatus'

export function HomePage() {
  return (
    <section>
      <h1 className="text-2xl font-semibold">Support Ticket &amp; SLA Management</h1>
      <p className="mt-2 text-slate-600">Backend connection status for this environment.</p>
      <BackendStatus />
    </section>
  )
}
