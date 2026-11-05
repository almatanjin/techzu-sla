import { Link } from 'react-router'

export function NotAuthorizedPage() {
  return (
    <div className="mx-auto max-w-md card p-6">
      <h1 className="text-xl font-bold text-fg">Not authorized</h1>
      <p className="mt-2 text-sm text-fg-muted">
        You do not have permission to view this page. Administrator privileges are required.
      </p>
      <div className="mt-4">
        <Link
          to="/"
          className="btn btn-secondary"
        >
          Return home
        </Link>
      </div>
    </div>
  )
}
