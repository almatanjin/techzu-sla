import type { AxiosInstance } from 'axios'

export type HealthResponse = {
  status: 'ok' | 'unavailable'
  checks: {
    database: 'ok' | 'unavailable'
  }
}

export async function fetchHealth(client: AxiosInstance): Promise<HealthResponse> {
  const { data } = await client.get<HealthResponse>('health')

  return data
}
