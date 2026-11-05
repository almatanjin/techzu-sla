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
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Categories</h1>
        <p className="mt-1 text-sm text-slate-600">
          Manage the ticket categories used to classify customer requests.
        </p>
      </div>

      {/* Add Category Form */}
      <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-base font-semibold text-slate-900">Add New Category</h2>
        <form onSubmit={handleSubmit(onAddCategory)} className="mt-4 max-w-lg space-y-4">
          <div>
            <label htmlFor="new-category-name" className="block text-sm font-medium text-slate-700">
              Category name
            </label>
            <div className="mt-1 flex gap-3">
              <input
                id="new-category-name"
                type="text"
                maxLength={100}
                placeholder="e.g. Shipping"
                {...register('name')}
                className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <button
                type="submit"
                disabled={isSubmitting}
                className="shrink-0 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
              >
                {isSubmitting ? 'Adding...' : 'Add Category'}
              </button>
            </div>
            {errors.name && (
              <p role="alert" className="mt-1.5 text-xs text-rose-600">
                {errors.name.message}
              </p>
            )}
            {formError && (
              <p role="alert" className="mt-1.5 text-xs text-rose-600">
                {formError}
              </p>
            )}
          </div>
        </form>
      </div>

      {/* Categories List */}
      {isLoading && (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center text-slate-500">
          Loading categories...
        </div>
      )}

      {isError && (
        <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-center text-rose-800">
          <p>Failed to load categories.</p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="mt-3 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500"
          >
            Retry
          </button>
        </div>
      )}

      {!isLoading && !isError && categories && (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          {categories.length === 0 ? (
            <div className="p-12 text-center text-slate-500">No categories found.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead className="bg-slate-50 text-slate-700">
                  <tr>
                    <th className="px-6 py-3 font-semibold">Name</th>
                    <th className="px-6 py-3 font-semibold">Status</th>
                    <th className="px-6 py-3 font-semibold text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 bg-white text-slate-900">
                  {categories.map((category) => {
                    const isEditing = editingId === category.id

                    return (
                      <tr key={category.id} className="hover:bg-slate-50">
                        <td className="px-6 py-4 font-medium">
                          {isEditing ? (
                            <div className="space-y-1">
                              <div className="flex items-center gap-2">
                                <input
                                  type="text"
                                  maxLength={100}
                                  value={editingName}
                                  onChange={(e) => setEditingName(e.target.value)}
                                  className="rounded border border-slate-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <button
                                  type="button"
                                  onClick={() => saveEditing(category.id)}
                                  disabled={renameMutation.isPending}
                                  className="rounded bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                                >
                                  Save
                                </button>
                                <button
                                  type="button"
                                  onClick={cancelEditing}
                                  className="rounded border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                  Cancel
                                </button>
                              </div>
                              {editingError && (
                                <p role="alert" className="text-xs text-rose-600">
                                  {editingError}
                                </p>
                              )}
                            </div>
                          ) : (
                            category.name
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <Badge variant={category.is_active ? 'emerald' : 'rose'}>
                            {category.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                        </td>
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end gap-3">
                            {!isEditing && (
                              <button
                                type="button"
                                onClick={() => startEditing(category)}
                                className="font-medium text-blue-600 hover:text-blue-500 hover:underline"
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
                                  ? 'text-rose-600 hover:text-rose-500'
                                  : 'text-emerald-600 hover:text-emerald-500'
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
