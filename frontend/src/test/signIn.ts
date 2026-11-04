import { http, HttpResponse } from 'msw'
import type { User } from '../features/auth/types'
import { server } from './server'

export const defaultTestUser: User = {
  id: 1,
  name: 'Demo Admin',
  email: 'admin@techzu-sla.test',
  role: 'admin',
  is_active: true,
  created_at: '2026-01-01T00:00:00.000Z',
}

export function signIn(user: Partial<User> = {}): User {
  const mergedUser: User = {
    ...defaultTestUser,
    ...user,
  }

  localStorage.setItem('techzu.token', 'test-token-123')

  server.use(
    http.get('*/api/v1/auth/me', () => {
      return HttpResponse.json(mergedUser)
    }),
  )

  return mergedUser
}

export function signOut(): void {
  localStorage.removeItem('techzu.token')
}

