import { useActiveCategories } from '../categories/useActiveCategories'

type Props = {
  isAvailable: boolean
  onIsAvailableChange: (val: boolean) => void
  maxOpenTickets: string
  onMaxOpenTicketsChange: (val: string) => void
  categoryIds: number[]
  onCategoryIdsChange: (ids: number[]) => void
}

export function AgentSettingsFields({
  isAvailable,
  onIsAvailableChange,
  maxOpenTickets,
  onMaxOpenTicketsChange,
  categoryIds,
  onCategoryIdsChange,
}: Props) {
  const { data: categories = [] } = useActiveCategories()

  const handleCategoryToggle = (categoryId: number) => {
    if (categoryIds.includes(categoryId)) {
      onCategoryIdsChange(categoryIds.filter((id) => id !== categoryId))
    } else {
      onCategoryIdsChange([...categoryIds, categoryId])
    }
  }

  return (
    <div className="pt-4 mt-4 border-t border-border-subtle space-y-4">
      <h3 className="text-base font-semibold text-fg">Assignment</h3>

      <div className="flex items-center gap-3">
        <input
          id="is_available"
          type="checkbox"
          checked={isAvailable}
          onChange={(e) => onIsAvailableChange(e.target.checked)}
          className="checkbox"
        />
        <label htmlFor="is_available" className="text-sm font-medium text-fg-secondary">
          Available for new tickets
        </label>
      </div>

      <div>
        <label htmlFor="max_open_tickets" className="label">
          Maximum active tickets
        </label>
        <input
          id="max_open_tickets"
          type="number"
          min="1"
          max="1000"
          value={maxOpenTickets}
          onChange={(e) => onMaxOpenTicketsChange(e.target.value)}
          placeholder="Leave empty for unlimited"
          className="mt-1 input"
        />
        <p className="mt-1 text-xs text-fg-subtle">
          Maximum number of open or in-progress tickets before skipping automatic assignment.
        </p>
      </div>

      {categories.length > 0 && (
        <div>
          <label className="label mb-2 block">Categories</label>
          <div className="space-y-2">
            {categories.map((category) => (
              <div key={category.id} className="flex items-center gap-3">
                <input
                  id={`category-${category.id}`}
                  type="checkbox"
                  checked={categoryIds.includes(category.id)}
                  onChange={() => handleCategoryToggle(category.id)}
                  className="checkbox"
                />
                <label
                  htmlFor={`category-${category.id}`}
                  className="text-sm text-fg-secondary"
                >
                  {category.name}
                </label>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

