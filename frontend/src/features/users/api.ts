import type { AxiosInstance } from 'axios'
import type { User } from '../auth/types'
import type {
  CreateUserPayload,
  PaginatedUsersResponse,
  UpdateUserPayload,
  UserFilters,
} from './types'

export async function listUsers(
  client: AxiosInstance,
  filters: UserFilters = {},
): Promise<PaginatedUsersResponse> {
  const params = new URLSearchParams()
  if (filters.role) params.set('role', filters.role)
  if (filters.status) params.set('status', filters.status)
  if (filters.search) params.set('search', filters.search)
  if (filters.page && filters.page > 1) params.set('page', String(filters.page))

  const response = await client.get<PaginatedUsersResponse>('admin/users', { params })
  return response.data
}

export async function getUser(client: AxiosInstance, id: number): Promise<User> {
  const response = await client.get<User>(`admin/users/${id}`)
  return response.data
}

export async function createUser(
  client: AxiosInstance,
  payload: CreateUserPayload,
): Promise<User> {
  const response = await client.post<User>('admin/users', payload)
  return response.data
}

export async function updateUser(
  client: AxiosInstance,
  id: number,
  payload: UpdateUserPayload,
): Promise<User> {
  const response = await client.patch<User>(`admin/users/${id}`, payload)
  return response.data
}

