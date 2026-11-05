import type { AxiosInstance } from 'axios'
import type {
  BusinessHourDay,
  BusinessHoursResponse,
  Holiday,
  SlaPolicy,
} from './types'

export async function listSlaPolicies(client: AxiosInstance): Promise<SlaPolicy[]> {
  const response = await client.get<SlaPolicy[] | { data: SlaPolicy[] }>('admin/sla-policies')
  const resData = response.data
  return Array.isArray(resData) ? resData : resData.data
}

export async function updateSlaPolicy(
  client: AxiosInstance,
  priority: string,
  payload: { resolution_minutes?: number; warning_threshold_percent?: number },
): Promise<SlaPolicy> {
  const response = await client.patch<SlaPolicy | { data: SlaPolicy }>(
    `admin/sla-policies/${priority}`,
    payload,
  )
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function getBusinessHours(client: AxiosInstance): Promise<BusinessHoursResponse> {
  const response = await client.get<BusinessHoursResponse | { data: BusinessHoursResponse }>(
    'admin/business-hours',
  )
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function updateBusinessHours(
  client: AxiosInstance,
  days: BusinessHourDay[],
): Promise<BusinessHoursResponse> {
  const response = await client.put<BusinessHoursResponse | { data: BusinessHoursResponse }>(
    'admin/business-hours',
    { days },
  )
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function listHolidays(client: AxiosInstance, year?: number): Promise<Holiday[]> {
  const params = new URLSearchParams()
  if (year !== undefined) {
    params.set('year', String(year))
  }
  const response = await client.get<Holiday[] | { data: Holiday[] }>('admin/holidays', { params })
  const resData = response.data
  return Array.isArray(resData) ? resData : resData.data
}

export async function createHoliday(
  client: AxiosInstance,
  payload: { date: string; name: string },
): Promise<Holiday> {
  const response = await client.post<Holiday | { data: Holiday }>('admin/holidays', payload)
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function deleteHoliday(client: AxiosInstance, id: number): Promise<void> {
  await client.delete(`admin/holidays/${id}`)
}

