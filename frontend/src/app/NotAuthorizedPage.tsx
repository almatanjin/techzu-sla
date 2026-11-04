import { Link } from 'react-router'

export function NotAuthorizedPage() {
  return (
    <div className="mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h1 className="text-xl font-bold text-slate-900">Not authorized</h1>
      <p className="mt-2 text-sm text-slate-600">
        You do not have permission to view this page. Administrator privileges are required.
      </p>
      <div className="mt-4">
        <Link
          to="/"
          className="inline-flex items-center rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        >
          Return home
        </Link>
      </div>
    </div>
  )
}

