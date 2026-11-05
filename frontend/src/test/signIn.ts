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
    http.get('*/api/v1/dashboard', () => {
      return HttpResponse.json({
        generated_at: '2026-11-05T10:00:00Z',
        team: {
          open: 0,
          unassigned: 0,
          critical: 0,
          sla_breached: 0,
          sla_approaching: 0,
          resolved_today: 0,
          resolved_last_7_days: 0,
        },
        my_queue: {
          open: 0,
          sla_approaching: 0,
          sla_breached: 0,
        },
        by_priority: [
          { priority: 'critical', count: 0 },
          { priority: 'high', count: 0 },
          { priority: 'medium', count: 0 },
          { priority: 'low', count: 0 },
        ],
      })
    }),
  )

  return mergedUser
}

export function signOut(): void {
  localStorage.removeItem('techzu.token')
}

