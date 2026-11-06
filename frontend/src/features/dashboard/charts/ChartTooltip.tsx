type Line = {
  key: string
  text: string
  /** Theme utility for the swatch, for example `bg-chart-created`. */
  swatchClassName?: string
}

type Props = {
  title: string
  lines: Line[]
}

/** The box shown when a chart point or bar is hovered or focused. */
export function ChartTooltip({ title, lines }: Props) {
  return (
    <div className="rounded-md border border-border bg-surface px-3 py-2 text-sm shadow-md">
      <p className="font-semibold text-fg">{title}</p>
      <ul className="mt-1 space-y-0.5">
        {lines.map((line) => (
          <li key={line.key} className="flex items-center gap-2 text-fg-secondary">
            {line.swatchClassName ? (
              <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${line.swatchClassName}`} />
            ) : null}
            <span>{line.text}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
