import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { RouterProvider } from 'react-router'
import { ApiClientContext } from '../shared/api/apiClientContext'
import { createApiClient } from '../shared/api/client'
import type { AppConfig } from '../shared/config'
import { createAppRouter } from './router'

type Props = {
  config: AppConfig
}

export function App({ config }: Props) {
  const [queryClient] = useState(() => new QueryClient())
  // Axios joins a relative path to the base URL only when the base ends with "/".
  const [apiClient] = useState(() => createApiClient(`${config.apiUrl}/`))
  const [router] = useState(createAppRouter)

  return (
    <QueryClientProvider client={queryClient}>
      <ApiClientContext.Provider value={apiClient}>
        <RouterProvider router={router} />
      </ApiClientContext.Provider>
    </QueryClientProvider>
  )
}
