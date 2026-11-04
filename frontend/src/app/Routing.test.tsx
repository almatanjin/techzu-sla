import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { signIn } from '../test/signIn'
import { Root } from './Root'

describe('routing', () => {
  beforeEach(() => {
    signIn()
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => window.history.replaceState(null, '', '/'))

  it('shows a Page not found page with a link home for unknown paths', async () => {
    window.history.replaceState(null, '', '/this-page-does-not-exist')

    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('link', { name: 'Back to home' }))
    expect(await screen.findByText('Connected')).toBeInTheDocument()
    expect(window.location.pathname).toBe('/')
  })
})
