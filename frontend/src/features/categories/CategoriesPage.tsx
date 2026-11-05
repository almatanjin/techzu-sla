import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useApiClient } from '../../shared/api/apiClientContext'
import { applyApiErrors } from '../../shared/api/applyApiErrors'
import { Badge } from '../../shared/components/Badge'
import { createCategory, listCategories, updateCategory } from './api'
import type { Category } from './types'

type AddCategoryValues = {
  name: string
}

export function CategoriesPage() {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()

  const [editingId, setEditingId] = useState<number | null>(null)
  const [editingName, setEditingName] = useState('')
  const [editingError, setEditingError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<AddCategoryValues>({
    defaultValues: {
      name: '',
    },
  })

  const {
    data: categories,
    isLoading,
    isError,
    refetch,
  } = useQuery<Category[]>({
    queryKey: ['admin', 'categories'],
    queryFn: () => listCategories(apiClient, true),
  })

  const invalidateQueries = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] }),
      queryClient.invalidateQueries({ queryKey: ['categories'] }),
    ])
  }

  const toggleActiveMutation = useMutation({
    mutationFn: async ({ id, is_active }: { id: number; is_active: boolean }) => {
      return updateCategory(apiClient, id, { is_active })
    },
    onSuccess: invalidateQueries,
  })

  const renameMutation = useMutation({
    mutationFn: async ({ id, name }: { id: number; name: string }) => {
      return updateCategory(apiClient, id, { name })
    },
    onSuccess: async () => {
      setEditingId(null)
      setEditingName('')
      setEditingError(null)
      await invalidateQueries()
    },
    onError: (err: unknown) => {
      applyApiErrors<{ name: string }>(
        err,
        (_field, error) => {
          setEditingError(error.message ?? 'Failed to update category name.')
        },
        (msg) => setEditingError(msg),
      )
    },
  })

  const onAddCategory = async (values: AddCategoryValues) => {
    setFormError(null)
    const trimmed = values.name.trim()
    if (!trimmed) {
      setError('name', { type: 'manual', message: 'Category name is required' })
      return
    }

    try {
      await createCategory(apiClient, { name: trimmed })
      reset()
      await invalidateQueries()
    } catch (err) {
      applyApiErrors<AddCategoryValues>(err, setError, setFormError)
    }
  }

  const startEditing = (category: Category) => {
    setEditingId(category.id)
    setEditingName(category.name)
    setEditingError(null)
  }

  const cancelEditing = () => {
    setEditingId(null)
    setEditingName('')
    setEditingError(null)
  }

  const saveEditing = (id: number) => {
    const trimmed = editingName.trim()
    if (!trimmed) {
      setEditingError('Category name is required')
      return
    }
    renameMutation.mutate({ id, name: trimmed })
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-fg">Categories</h1>
        <p className="mt-1 text-sm text-fg-muted">
          Manage the ticket categories used to classify customer requests.
        </p>
      </div>

      {/* Add Category Form */}
      <div className="card p-6">
        <h2 className="text-base font-semibold text-fg">Add New Category</h2>
        <form onSubmit={handleSubmit(onAddCategory)} className="mt-4 max-w-lg space-y-4">
          <div>
            <label htmlFor="new-category-name" className="label">
              Category name
            </label>
            <div className="mt-1 flex gap-3">
              <input
                id="new-category-name"
                type="text"
                maxLength={100}
                placeholder="e.g. Shipping"
                {...register('name')}
                className="input"
              />
              <button
                type="submit"
                disabled={isSubmitting}
                className="shrink-0 btn btn-primary"
              >
                {isSubmitting ? 'Adding...' : 'Add Category'}
              </button>
            </div>
            {errors.name && (
              <p role="alert" className="mt-1.5 text-xs text-danger">
                {errors.name.message}
              </p>
            )}
            {formError && (
              <p role="alert" className="mt-1.5 text-xs text-danger">
                {formError}
              </p>
            )}
          </div>
        </form>
      </div>

      {/* Categories List */}
      {isLoading && (
        <div className="card p-12 text-center text-fg-subtle">
          Loading categories...
        </div>
      )}

      {isError && (
        <div role="alert" className="alert alert-danger p-6 text-center">
          <p>Failed to load categories.</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-3 btn btn-danger btn-sm"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && categories && (
        <div className="overflow-hidden card">
          {categories.length === 0 ? (
            <div className="p-12 text-center text-fg-subtle">No categories found.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-border text-left text-sm">
                <thead className="bg-surface-muted text-fg-secondary">
                  <tr>
                    <th className="px-6 py-3 font-semibold">Name</th>
                    <th className="px-6 py-3 font-semibold">Status</th>
                    <th className="px-6 py-3 font-semibold text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border bg-surface text-fg">
                  {categories.map((category) => {
                    const isEditing = editingId === category.id

                    return (
                      <tr key={category.id} className="hover:bg-surface-muted">
                        <td className="px-6 py-4 font-medium">
                          {isEditing ? (
                            <div className="space-y-1">
                              <div className="flex items-center gap-2">
                                <input
                                  type="text"
                                  maxLength={100}
                                  value={editingName}
                                  onChange={(e) => setEditingName(e.target.value)}
                                  className="input w-auto py-1 px-2 text-sm"
                                />
                                <button
                                  type="button"
                                  onClick={() => saveEditing(category.id)}
                                  disabled={renameMutation.isPending}
                                  className="btn btn-primary btn-xs"
                                >
                                  Save
                                </button>
                                <button
                                  type="button"
                                  onClick={cancelEditing}
                                  className="btn btn-outline btn-xs"
                                >
                                  Cancel
                                </button>
                              </div>
                              {editingError && (
                                <p role="alert" className="text-xs text-danger">
                                  {editingError}
                                </p>
                              )}
                            </div>
                          ) : (
                            category.name
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <Badge variant={category.is_active ? 'success' : 'danger'}>
                            {category.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                        </td>
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end gap-3">
                            {!isEditing && (
                              <button
                                type="button"
                                onClick={() => startEditing(category)}
                                className="link font-medium"
                              >
                                Rename
                              </button>
                            )}
                            <button
                              type="button"
                              onClick={() =>
                                toggleActiveMutation.mutate({
                                  id: category.id,
                                  is_active: !category.is_active,
                                })
                              }
                              disabled={toggleActiveMutation.isPending}
                              className={`text-xs font-semibold hover:underline ${
                                category.is_active
                                  ? 'text-danger hover:text-danger-hover'
                                  : 'text-success hover:text-success-hover'
                              }`}
                            >
                              {category.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
