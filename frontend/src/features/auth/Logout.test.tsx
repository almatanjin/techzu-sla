import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('Logout flow', () => {
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

  it('calls the logout API, clears stored session, and navigates to the login page', async () => {
    const user = userEvent.setup()
    let logoutCalled = false

    server.use(
      http.post('http://api.test/api/v1/auth/logout', () => {
        logoutCalled = true
        return new HttpResponse(null, { status: 204 })
      }),
    )

    signIn()
    render(<Root />)

    expect(await screen.findByRole('button', { name: 'Log out' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Log out' }))

    expect(await screen.findByText('Sign in to your account')).toBeInTheDocument()
    expect(logoutCalled).toBe(true)
    expect(localStorage.getItem('techzu.token')).toBeNull()
    expect(window.location.pathname).toBe('/login')
  })
})

