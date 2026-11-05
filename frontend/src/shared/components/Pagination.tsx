import type { PaginationMeta } from '../api/types'

type Props = {
  meta: PaginationMeta
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: Props) {
  const { current_page, last_page, from, to, total } = meta

  if (total === 0 || last_page <= 1) {
    return null
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-4 border-t border-border bg-surface px-4 py-3 sm:px-6">
      <div className="text-sm text-fg-secondary">
        Showing <span className="font-medium">{from ?? 0}</span> to{' '}
        <span className="font-medium">{to ?? 0}</span> of{' '}
        <span className="font-medium">{total}</span> results
      </div>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onPageChange(current_page - 1)}
          disabled={current_page <= 1}
          className="btn btn-outline btn-sm"
        >
          Previous
        </button>
        <span className="text-sm text-fg-muted">
          Page {current_page} of {last_page}
        </span>
        <button
          type="button"
          onClick={() => onPageChange(current_page + 1)}
          disabled={current_page >= last_page}
          className="btn btn-outline btn-sm"
        >
          Next
        </button>
      </div>
    </div>
  )
}
