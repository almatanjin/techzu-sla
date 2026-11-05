import { Link } from 'react-router'

export function NotFoundPage() {
  return (
    <section className="py-12 text-center">
      <h1 className="text-2xl font-semibold">Page not found</h1>
      <p className="mt-2 text-fg-muted">The page you are looking for does not exist.</p>
      <Link
        to="/"
        className="mt-6 btn btn-secondary"
      >
        Back to home
      </Link>
    </section>
  )
}
