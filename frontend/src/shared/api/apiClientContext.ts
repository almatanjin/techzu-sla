import type { AxiosInstance } from 'axios'
import { createContext, useContext } from 'react'

export const ApiClientContext = createContext<AxiosInstance | null>(null)

export function useApiClient(): AxiosInstance {
  const client = useContext(ApiClientContext)

  if (!client) {
    throw new Error('useApiClient must be used inside ApiClientContext.Provider')
  }

  return client
}
