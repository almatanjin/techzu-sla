import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { delay, http, HttpResponse } from 'msw'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('backend status', () => {
  beforeEach(() => {
    signIn()
  })
  it('requests the health endpoint at the configured API URL', async () => {
    vi.stubEnv('VITE_API_URL', 'https://api.example.test/api/v1/')
    const requestedUrls: string[] = []
    server.use(
      http.get('https://api.example.test/api/v1/health', ({ request }) => {
        requestedUrls.push(request.url)
        return HttpResponse.json({ status: 'ok', checks: { database: 'ok' } })
      }),
    )

    render(<Root />)

    await waitFor(() => expect(requestedUrls).toEqual(['https://api.example.test/api/v1/health']))
  })
  it('shows a loading indicator while the health request is in flight', async () => {
    server.use(
      http.get('http://api.test/api/v1/health', async () => {
        await delay('infinite')
        return HttpResponse.json({})
      }),
    )

    render(<Root />)

    expect(await screen.findByRole('status')).toHaveTextContent('Checking backend')
  })

  it('shows Connected when the backend is healthy', async () => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )

    render(<Root />)

    expect(await screen.findByText('Connected')).toBeInTheDocument()
  })
  it('shows Unavailable with a Retry button when the backend reports 503', async () => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'unavailable', checks: { database: 'unavailable' } }, { status: 503 }),
      ),
    )

    render(<Root />)

    expect(await screen.findByText('Unavailable')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('shows Unavailable when the request fails at the network level', async () => {
    server.use(http.get('http://api.test/api/v1/health', () => HttpResponse.error()))

    render(<Root />)

    expect(await screen.findByText('Unavailable')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('shows Connected after Retry once the backend recovers', async () => {
    let healthy = false
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        healthy
          ? HttpResponse.json({ status: 'ok', checks: { database: 'ok' } })
          : HttpResponse.json({ status: 'unavailable', checks: { database: 'unavailable' } }, { status: 503 }),
      ),
    )
    render(<Root />)
    const retry = await screen.findByRole('button', { name: 'Retry' })

    healthy = true
    await userEvent.click(retry)

    expect(await screen.findByText('Connected')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
  })
})
