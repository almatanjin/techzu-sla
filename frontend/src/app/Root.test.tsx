import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { Root } from './Root'

describe('configuration', () => {
  it('shows a configuration error instead of the app when VITE_API_URL is missing', () => {
    vi.stubEnv('VITE_API_URL', '')

    render(<Root />)

    expect(screen.getByRole('alert')).toHaveTextContent('VITE_API_URL')
  })
})
