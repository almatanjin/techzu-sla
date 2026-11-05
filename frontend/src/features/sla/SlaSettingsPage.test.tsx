import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('SlaSettingsPage - Policies & Authorization', () => {
  const initialPolicies = [
    { id: 1, priority: 'low', resolution_minutes: 2880, warning_threshold_percent: 75 },
    { id: 2, priority: 'medium', resolution_minutes: 1440, warning_threshold_percent: 75 },
    { id: 3, priority: 'high', resolution_minutes: 480, warning_threshold_percent: 75 },
    { id: 4, priority: 'critical', resolution_minutes: 120, warning_threshold_percent: 75 },
  ]

  const initialBusinessHours = {
    timezone: 'Asia/Dhaka',
    days: [
      { id: 1, day_of_week: 0, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 2, day_of_week: 1, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 3, day_of_week: 2, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 4, day_of_week: 3, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 5, day_of_week: 4, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 6, day_of_week: 5, is_working_day: false, opens_at: null, closes_at: null },
      { id: 7, day_of_week: 6, is_working_day: false, opens_at: null, closes_at: null },
    ],
  }

  let currentPolicies = [...initialPolicies]

  beforeEach(() => {
    currentPolicies = initialPolicies.map((p) => ({ ...p }))
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/admin/sla-policies', () => {
        return HttpResponse.json({ data: currentPolicies })
      }),
      http.get('http://api.test/api/v1/admin/business-hours', () => {
        return HttpResponse.json(initialBusinessHours)
      }),
      http.get('http://api.test/api/v1/admin/holidays', () => {
        return HttpResponse.json({ data: [] })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('renders "SLA" navigation link for admin and shows "Not authorized" for agent at /admin/sla', async () => {
    // 1. Agent visits /admin/sla
    signIn({ role: 'agent' })
    window.history.replaceState(null, '', '/admin/sla')
    const { unmount } = render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Not authorized' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'SLA' })).not.toBeInTheDocument()

    unmount()

    // 2. Admin visits /admin/sla
    signIn({ role: 'admin' })
    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: /SLA/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'SLA' })).toBeInTheDocument()
  })

  it('allows editing Critical policy to 1 h 30 min and displays formatted duration', async () => {
    signIn({ role: 'admin' })
    const user = userEvent.setup()
    let capturedPatch: unknown = null

    server.use(
      http.patch('http://api.test/api/v1/admin/sla-policies/critical', async ({ request }) => {
        capturedPatch = await request.json()
        const updatedPolicy = {
          id: 4,
          priority: 'critical' as const,
          resolution_minutes: 90,
          warning_threshold_percent: 75,
        }
        currentPolicies = currentPolicies.map((p) => (p.priority === 'critical' ? updatedPolicy : p))
        return HttpResponse.json({
          data: updatedPolicy,
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: /SLA/i })).toBeInTheDocument()
    expect(await screen.findByText('2 business hours')).toBeInTheDocument()

    const hoursInput = screen.getByLabelText('Critical resolution hours') as HTMLInputElement
    const minutesInput = screen.getByLabelText('Critical resolution minutes') as HTMLInputElement

    await user.clear(hoursInput)
    await user.type(hoursInput, '1')
    await user.clear(minutesInput)
    await user.type(minutesInput, '30')

    await user.click(screen.getByRole('button', { name: 'Save Critical Policy' }))

    expect(capturedPatch).toEqual({
      resolution_minutes: 90,
      warning_threshold_percent: 75,
    })

    expect(await screen.findByText('1 h 30 min')).toBeInTheDocument()
  })

  it('displays API validation error message under the field when receiving 422', async () => {
    signIn({ role: 'admin' })
    const user = userEvent.setup()

    server.use(
      http.patch('http://api.test/api/v1/admin/sla-policies/critical', () => {
        return HttpResponse.json(
          {
            message: 'The given data was invalid.',
            errors: {
              resolution_minutes: ['The resolution time must be between 15 minutes and 30 days.'],
            },
          },
          { status: 422 },
        )
      }),
    )

    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: /SLA/i })).toBeInTheDocument()
    expect(await screen.findByText('2 business hours')).toBeInTheDocument()

    const minutesInput = screen.getByLabelText('Critical resolution minutes') as HTMLInputElement
    const hoursInput = screen.getByLabelText('Critical resolution hours') as HTMLInputElement

    await user.clear(hoursInput)
    await user.type(hoursInput, '0')
    await user.clear(minutesInput)
    await user.type(minutesInput, '5')

    await user.click(screen.getByRole('button', { name: 'Save Critical Policy' }))

    expect(
      await screen.findByText('The resolution time must be between 15 minutes and 30 days.'),
    ).toBeInTheDocument()
  })
})

describe('SlaSettingsPage - Business Hours & Holidays', () => {
  const initialPolicies = [
    { id: 1, priority: 'low', resolution_minutes: 2880, warning_threshold_percent: 75 },
    { id: 2, priority: 'medium', resolution_minutes: 1440, warning_threshold_percent: 75 },
    { id: 3, priority: 'high', resolution_minutes: 480, warning_threshold_percent: 75 },
    { id: 4, priority: 'critical', resolution_minutes: 120, warning_threshold_percent: 75 },
  ]

  const initialBusinessHours = {
    timezone: 'Asia/Dhaka',
    days: [
      { id: 1, day_of_week: 0, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 2, day_of_week: 1, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 3, day_of_week: 2, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 4, day_of_week: 3, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 5, day_of_week: 4, is_working_day: true, opens_at: '09:00', closes_at: '18:00' },
      { id: 6, day_of_week: 5, is_working_day: false, opens_at: null, closes_at: null },
      { id: 7, day_of_week: 6, is_working_day: false, opens_at: null, closes_at: null },
    ],
  }

  beforeEach(() => {
    signIn({ role: 'admin' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
      http.get('http://api.test/api/v1/admin/sla-policies', () => {
        return HttpResponse.json({ data: initialPolicies })
      }),
      http.get('http://api.test/api/v1/admin/business-hours', () => {
        return HttpResponse.json(initialBusinessHours)
      }),
      http.get('http://api.test/api/v1/admin/holidays', () => {
        return HttpResponse.json({ data: [] })
      }),
    )
  })

  afterEach(() => {
    window.history.replaceState(null, '', '/')
  })

  it('unticking Thursday and saving sends PUT with Thursday not working', async () => {
    const user = userEvent.setup()
    let capturedPutDays: unknown = null

    server.use(
      http.put('http://api.test/api/v1/admin/business-hours', async ({ request }) => {
        const body = (await request.json()) as { days: unknown }
        capturedPutDays = body.days
        return HttpResponse.json({
          timezone: 'Asia/Dhaka',
          days: body.days,
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Business Hours' })).toBeInTheDocument()
    expect(await screen.findByText(/Timezone: Asia\/Dhaka/i)).toBeInTheDocument()

    const thursdayCheckbox = screen.getByLabelText('Thursday working day') as HTMLInputElement
    expect(thursdayCheckbox.checked).toBe(true)

    await user.click(thursdayCheckbox)
    expect(thursdayCheckbox.checked).toBe(false)

    await user.click(screen.getByRole('button', { name: 'Save Business Hours' }))

    expect(capturedPutDays).toBeDefined()
    const daysArr = capturedPutDays as Array<{ day_of_week: number; is_working_day: boolean }>
    const thursday = daysArr.find((d) => d.day_of_week === 4)
    expect(thursday?.is_working_day).toBe(false)
  })

  it('displays error on days.0.closes_at under Sunday closing time', async () => {
    const user = userEvent.setup()

    server.use(
      http.put('http://api.test/api/v1/admin/business-hours', () => {
        return HttpResponse.json(
          {
            message: 'The given data was invalid.',
            errors: {
              'days.0.closes_at': [
                'The closing time must be after the opening time on the same day.',
              ],
            },
          },
          { status: 422 },
        )
      }),
    )

    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Business Hours' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Save Business Hours' }))

    expect(
      await screen.findByText('The closing time must be after the opening time on the same day.'),
    ).toBeInTheDocument()
  })

  it('lists holidays, adds a holiday, deletes one, and displays duplicate-date error', async () => {
    const user = userEvent.setup()
    let holidaysList = [
      { id: 10, date: '2026-02-21', name: 'Martyrs Day' },
    ]
    let capturedPost: unknown = null
    let deletedId: string | null = null

    server.use(
      http.get('http://api.test/api/v1/admin/holidays', () => {
        return HttpResponse.json({ data: holidaysList })
      }),
      http.post('http://api.test/api/v1/admin/holidays', async ({ request }) => {
        const body = (await request.json()) as { date: string; name: string }
        capturedPost = body
        if (body.date === '2026-02-21') {
          return HttpResponse.json(
            {
              message: 'The given data was invalid.',
              errors: {
                date: ['A holiday on this date already exists.'],
              },
            },
            { status: 422 },
          )
        }
        const created = { id: 11, date: body.date, name: body.name }
        holidaysList = [...holidaysList, created]
        return HttpResponse.json({ data: created }, { status: 201 })
      }),
      http.delete('http://api.test/api/v1/admin/holidays/:id', ({ params }) => {
        deletedId = String(params.id)
        holidaysList = holidaysList.filter((h) => String(h.id) !== deletedId)
        return new HttpResponse(null, { status: 204 })
      }),
    )

    window.history.replaceState(null, '', '/admin/sla')
    render(<Root />)

    // 1. Initial list
    expect(await screen.findByText('Martyrs Day')).toBeInTheDocument()
    expect(screen.getByText('2026-02-21')).toBeInTheDocument()

    // 2. Add holiday duplicate error
    const dateInput = screen.getByLabelText('Holiday date')
    const nameInput = screen.getByLabelText('Holiday name')

    await user.type(dateInput, '2026-02-21')
    await user.type(nameInput, 'Duplicate Martyrs Day')
    await user.click(screen.getByRole('button', { name: 'Add Holiday' }))

    expect(await screen.findByText('A holiday on this date already exists.')).toBeInTheDocument()

    // 3. Add valid holiday
    await user.clear(dateInput)
    await user.type(dateInput, '2026-12-16')
    await user.clear(nameInput)
    await user.type(nameInput, 'Victory Day')
    await user.click(screen.getByRole('button', { name: 'Add Holiday' }))

    expect(capturedPost).toEqual({ date: '2026-12-16', name: 'Victory Day' })
    expect(await screen.findByText('Victory Day')).toBeInTheDocument()

    // 4. Delete holiday
    const deleteBtn = screen.getByRole('button', { name: 'Delete Victory Day' })
    await user.click(deleteBtn)

    expect(deletedId).toBe('11')
    await waitFor(() => {
      expect(screen.queryByText('Victory Day')).not.toBeInTheDocument()
    })
  })
})
