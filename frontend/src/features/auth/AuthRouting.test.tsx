import { render, screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('auth routing and guards', () => {
  beforeEach(() => {
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('redirects signed-out visitors from / to /login', async () => {
    window.history.replaceState(null, '', '/')

    render(<Root />)

    expect(await screen.findByText('Sign in to your account')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/login')
  })

  it('redirects signed-in users from /login to /', async () => {
    signIn()
    window.history.replaceState(null, '', '/login')

    render(<Root />)

    expect(await screen.findByText('Connected')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/')
  })

  it('shows Users nav link to administrators but hides it from non-admins', async () => {
    // 1. Signed in as Admin
    signIn({ role: 'admin' })
    render(<Root />)

    expect(await screen.findByRole('link', { name: 'Users' })).toBeInTheDocument()
  })

  it('hides Users nav link from customers', async () => {
    signIn({ role: 'customer' })
    render(<Root />)

    expect(await screen.findByText('Connected')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
  })

  it('shows Not authorized page when a customer accesses admin routes', async () => {
    signIn({ role: 'customer' })
    window.history.replaceState(null, '', '/admin/users')

    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Not authorized' })).toBeInTheDocument()
    expect(
      screen.getByText(/You do not have permission to view this page/i),
    ).toBeInTheDocument()
  })

  it('shows Tickets nav link to all authenticated users', async () => {
    signIn({ role: 'customer' })
    render(<Root />)

    expect(await screen.findByRole('link', { name: 'Tickets' })).toBeInTheDocument()
  })

  it('shows Categories nav link to administrators and hides it from agents and customers', async () => {
    signIn({ role: 'admin' })
    const { unmount } = render(<Root />)

    expect(await screen.findByRole('link', { name: 'Categories' })).toBeInTheDocument()
    unmount()

    signIn({ role: 'agent' })
    const agentRender = render(<Root />)
    expect(await agentRender.findByText('Connected')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Categories' })).not.toBeInTheDocument()
    agentRender.unmount()

    signIn({ role: 'customer' })
    const customerRender = render(<Root />)
    expect(await customerRender.findByText('Connected')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Categories' })).not.toBeInTheDocument()
  })

  it('shows Not authorized page when an agent accesses /admin/categories', async () => {
    signIn({ role: 'agent' })
    window.history.replaceState(null, '', '/admin/categories')

    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Not authorized' })).toBeInTheDocument()
    expect(
      screen.getByText(/You do not have permission to view this page/i),
    ).toBeInTheDocument()
  })

  it('shows Not authorized page when a customer accesses /admin/sla', async () => {
    signIn({ role: 'customer' })
    window.history.replaceState(null, '', '/admin/sla')

    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Not authorized' })).toBeInTheDocument()
    expect(
      screen.getByText(/You do not have permission to view this page/i),
    ).toBeInTheDocument()
  })
})

