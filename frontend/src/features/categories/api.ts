import type { AxiosInstance } from 'axios'
import type {
  Category,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from './types'

export async function listCategories(
  client: AxiosInstance,
  includeInactive = false,
): Promise<Category[]> {
  const params = new URLSearchParams()
  if (includeInactive) {
    params.set('include_inactive', '1')
  }

  const response = await client.get<Category[] | { data: Category[] }>('categories', { params })
  const resData = response.data
  return Array.isArray(resData) ? resData : resData.data
}

export async function createCategory(
  client: AxiosInstance,
  payload: CreateCategoryPayload,
): Promise<Category> {
  const response = await client.post<Category | { data: Category }>('admin/categories', payload)
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function updateCategory(
  client: AxiosInstance,
  id: number,
  payload: UpdateCategoryPayload,
): Promise<Category> {
  const response = await client.patch<Category | { data: Category }>(`admin/categories/${id}`, payload)
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}
