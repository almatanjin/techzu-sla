import { useQuery } from '@tanstack/react-query'
import { useApiClient } from '../../shared/api/apiClientContext'
import { listCategories } from './api'
import type { Category } from './types'

export function useActiveCategories() {
  const apiClient = useApiClient()

  return useQuery<Category[]>({
    queryKey: ['categories', { activeOnly: true }],
    queryFn: () => listCategories(apiClient, false),
  })
}

