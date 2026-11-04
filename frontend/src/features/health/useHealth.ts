import { useQuery } from '@tanstack/react-query'
import { useApiClient } from '../../shared/api/apiClientContext'
import { fetchHealth } from './api'

export function useHealth() {
  const client = useApiClient()

  return useQuery({
    queryKey: ['health'],
    queryFn: () => fetchHealth(client),
    // Show failures immediately; the user retries explicitly.
    retry: false,
  })
}
