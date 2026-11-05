import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('CategoriesPage', () => {
  let categoriesData: Array<{ id: number; name: string; is_active: boolean }> = []

  beforeEach(() => {
    categoriesData = [
      { id: 1, name: 'Billing', is_active: true },
      { id: 2, name: 'Technical', is_active: true },
      { id: 3, name: 'Legacy', is_active: false },
    ]

    signIn({ role: 'admin' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/categories', ({ request }) => {
        const url = new URL(request.url)
        const includeInactive = url.searchParams.get('include_inactive') === '1'
        const data = includeInactive
          ? categoriesData
          : categoriesData.filter((c) => c.is_active)
        return HttpResponse.json({ data })
      }),
      http.post('http://api.test/api/v1/admin/categories', async ({ request }) => {
        const body = (await request.json()) as { name: string }
        const newCat = { id: categoriesData.length + 1, name: body.name, is_active: true }
        categoriesData.push(newCat)
        return HttpResponse.json({ data: newCat }, { status: 201 })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('renders categories list with their status', async () => {
    window.history.replaceState(null, '', '/admin/categories')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Categories' })).toBeInTheDocument()
    expect(await screen.findByText('Billing')).toBeInTheDocument()
    expect(screen.getByText('Technical')).toBeInTheDocument()
    expect(screen.getByText('Legacy')).toBeInTheDocument()
    expect(screen.getAllByText('Active').length).toBeGreaterThan(0)
    expect(screen.getByText('Inactive')).toBeInTheDocument()
  })

  it('adds a category successfully', async () => {
    const user = userEvent.setup()

    window.history.replaceState(null, '', '/admin/categories')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Categories' })).toBeInTheDocument()
    expect(await screen.findByText('Billing')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Category name'), 'Shipping')
    await user.click(screen.getByRole('button', { name: 'Add Category' }))

    expect(await screen.findByText('Shipping')).toBeInTheDocument()
  })

  it('displays API error under the name field when duplicate name is submitted', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('http://api.test/api/v1/admin/categories', async () => {
        return HttpResponse.json(
          {
            message: 'The name has already been taken.',
            errors: {
              name: ['The name has already been taken.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    window.history.replaceState(null, '', '/admin/categories')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Categories' })).toBeInTheDocument()
    expect(await screen.findByText('Billing')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Category name'), 'billing')
    await user.click(screen.getByRole('button', { name: 'Add Category' }))

    expect(await screen.findByText('The name has already been taken.')).toBeInTheDocument()
  })
})
