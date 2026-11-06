import { useId, useState, type ReactNode } from 'react'
import { Link } from 'react-router'

export type LegendItem = {
  key: string
  label: string
  /** Theme utility for the swatch, for example `bg-sla-breached`. */
  swatchClassName: string
  /** Text after the label, for example `6 (60%)`. */
  value?: string
  /** When set, the legend item links to this ticket list. */
  to?: string
}

export type ChartTable = {
  columns: string[]
  rows: Array<Array<string | number>>
}

type Props = {
  title: string
  isEmpty: boolean
  emptyText: string
  legend?: LegendItem[]
  table?: ChartTable
  className?: string
  /** The chart itself; not rendered when `isEmpty` is true. */
  children: ReactNode
}

/** The shared frame of a dashboard chart: heading, chart or empty text, text legend and optional table. */
export function ChartCard({ title, isEmpty, emptyText, legend, table, className = '', children }: Props) {
  const headingId = useId()
  const tableId = useId()
  const [showTable, setShowTable] = useState(false)

  return (
    <section aria-labelledby={headingId} className={`card min-w-0 space-y-3 p-4 ${className}`}>
      <h2 id={headingId} className="text-lg font-semibold">
        {title}
      </h2>

      {isEmpty ? (
        <p className="py-6 text-sm text-fg-muted">{emptyText}</p>
      ) : (
        <>
          {children}

          {legend ? (
            <ul className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
              {legend.map((item) => {
                const content = (
                  <>
                    <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${item.swatchClassName}`} />
                    <span>
                      {item.label}
                      {item.value === undefined ? null : `: ${item.value}`}
                    </span>
                  </>
                )

                return (
                  <li key={item.key}>
                    {item.to ? (
                      <Link to={item.to} className="inline-flex items-center gap-2 font-medium hover:underline">
                        {content}
                      </Link>
                    ) : (
                      <span className="inline-flex items-center gap-2 font-medium">{content}</span>
                    )}
                  </li>
                )
              })}
            </ul>
          ) : null}

          {table ? (
            <div className="space-y-3">
              <button
                type="button"
                className="btn btn-outline btn-xs"
                aria-expanded={showTable}
                aria-controls={tableId}
                onClick={() => setShowTable((shown) => !shown)}
              >
                {showTable ? 'Hide table' : 'Show table'}
              </button>

              {showTable ? (
                <div id={tableId} className="overflow-x-auto rounded-lg border border-border">
                  <table className="w-full text-left text-sm">
                    <caption className="sr-only">{title}</caption>
                    <thead className="border-b border-border bg-surface-muted text-xs uppercase text-fg-muted">
                      <tr>
                        {table.columns.map((column, index) => (
                          <th
                            key={column}
                            scope="col"
                            className={`px-3 py-2 font-semibold ${index === 0 ? '' : 'text-right'}`}
                          >
                            {column}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {table.rows.map((row) => (
                        <tr key={String(row[0])}>
                          {row.map((cell, index) =>
                            index === 0 ? (
                              <th key={index} scope="row" className="px-3 py-2 font-medium whitespace-nowrap">
                                {cell}
                              </th>
                            ) : (
                              <td key={index} className="px-3 py-2 text-right tabular-nums">
                                {cell}
                              </td>
                            ),
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </div>
          ) : null}
        </>
      )}
    </section>
  )
}
