import axios, { type AxiosInstance } from 'axios'

export function createApiClient(baseURL: string): AxiosInstance {
  return axios.create({
    baseURL,
    adapter: 'fetch',
    timeout: 10_000,
    headers: { Accept: 'application/json' },
  })
}
