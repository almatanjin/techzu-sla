import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('UsersListPage', () => {
  beforeEach(() => {
    signIn({ role: 'admin' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('requests and displays only agents when opening /admin/users?role=agent', async () => {
    let capturedRole: string | null = null

    server.use(
      http.get('http://api.test/api/v1/admin/users', ({ request }) => {
        const url = new URL(request.url)
        capturedRole = url.searchParams.get('role')

        return HttpResponse.json({
          data: [
            {
              id: 10,
              name: 'Agent James',
              email: 'james@example.test',
              role: 'agent',
              is_active: true,
              created_at: '2026-01-01T00:00:00Z',
            },
            {
              id: 11,
              name: 'Agent Fox',
              email: 'fox@example.test',
              role: 'agent',
              is_active: true,
              created_at: '2026-01-01T00:00:00Z',
            },
          ],
          links: {},
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 2,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/users?role=agent')
    render(<Root />)

    expect(await screen.findAllByText('Agent James')).toHaveLength(2)
    expect(screen.getAllByText('Agent Fox')).toHaveLength(2)
    expect(capturedRole).toBe('agent')

    const roleSelect = screen.getByLabelText('Role:') as HTMLSelectElement
    expect(roleSelect.value).toBe('agent')
  })

  it('updates the URL when changing filter selections', async () => {
    const user = userEvent.setup()

    server.use(
      http.get('http://api.test/api/v1/admin/users', () => {
        return HttpResponse.json({
          data: [],
          links: {},
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/users')
    render(<Root />)

    await screen.findByText('No users found.')

    const roleSelect = screen.getByLabelText('Role:')
    await user.selectOptions(roleSelect, 'customer')

    expect(window.location.search).toContain('role=customer')

    const statusSelect = screen.getByLabelText('Status:')
    await user.selectOptions(statusSelect, 'inactive')

    expect(window.location.search).toContain('status=inactive')

    const searchInput = screen.getByPlaceholderText('Search by name or email...')
    await user.type(searchInput, 'alice')

    await waitFor(() => {
      expect(window.location.search).toContain('search=alice')
    })
  })
})
