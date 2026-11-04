import axios, { type AxiosInstance } from 'axios'
import { tokenStorage } from '../../features/auth/tokenStorage'

type UnauthorizedHandler = () => void

let unauthorizedHandler: UnauthorizedHandler | null = null

export function registerUnauthorizedHandler(handler: UnauthorizedHandler | null): () => void {
  unauthorizedHandler = handler
  return () => {
    if (unauthorizedHandler === handler) {
      unauthorizedHandler = null
    }
  }
}

export function createApiClient(baseURL: string): AxiosInstance {
  const client = axios.create({
    baseURL,
    adapter: 'fetch',
    timeout: 10_000,
    headers: { Accept: 'application/json' },
  })

  client.interceptors.request.use((config) => {
    const token = tokenStorage.getToken()
    if (token && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  })

  client.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
      if (axios.isAxiosError(error) && error.response?.status === 401) {
        const hasAuthHeader = Boolean(error.config?.headers?.Authorization)
        if (hasAuthHeader && unauthorizedHandler) {
          unauthorizedHandler()
        }
      }
      return Promise.reject(error)
    },
  )

  return client
}
