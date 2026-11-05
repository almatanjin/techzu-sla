import type { AxiosInstance } from 'axios'
import type { DashboardResponse } from './types'

export async function fetchDashboard(client: AxiosInstance): Promise<DashboardResponse> {
  const { data } = await client.get<DashboardResponse>('dashboard')
  return data
}

