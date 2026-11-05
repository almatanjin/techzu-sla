import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { Root } from '../../app/Root'
import { server } from '../../test/server'
import { signIn } from '../../test/signIn'

describe('ImportsPage', () => {
  let originalCreateObjectURL: typeof window.URL.createObjectURL
  let originalRevokeObjectURL: typeof window.URL.revokeObjectURL
  let clickSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    originalCreateObjectURL = window.URL.createObjectURL
    originalRevokeObjectURL = window.URL.revokeObjectURL
    window.URL.createObjectURL = vi.fn().mockReturnValue('blob:http://api.test/mock-object-url')
    window.URL.revokeObjectURL = vi.fn()
    clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    signIn({ role: 'admin', name: 'Sara Admin' })
    server.use(
      http.get('http://api.test/api/v1/health', () =>
        HttpResponse.json({ status: 'ok', checks: { database: 'ok' } }),
      ),
    )
  })

  afterEach(() => {
    window.URL.createObjectURL = originalCreateObjectURL
    window.URL.revokeObjectURL = originalRevokeObjectURL
    clickSpy.mockRestore()
    window.history.replaceState(null, '', '/')
  })

  it('uploads a file and polls progress from processing to completed', async () => {
    let pollCount = 0
    let importsList: Array<{
      id: number
      original_filename: string
      status: string
      total_rows: number
      processed_rows: number
      imported_rows: number
      failed_rows: number
      duplicate_rows: number
      failure_message: string | null
      user: { id: number; name: string }
      user_name: string
      started_at: string
      finished_at: string | null
      created_at: string
      updated_at: string
    }> = []

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        pollCount++
        if (pollCount > 2 && importsList.length > 0) {
          importsList[0] = {
            ...importsList[0],
            status: 'completed',
            processed_rows: 3,
            imported_rows: 3,
            finished_at: '2026-11-06T00:00:05Z',
          }
        }
        return HttpResponse.json({
          data: importsList,
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: importsList.length,
          },
        })
      }),
      http.post('http://api.test/api/v1/admin/imports', async () => {
        const item = {
          id: 1,
          original_filename: 'sample.csv',
          status: 'processing',
          total_rows: 3,
          processed_rows: 0,
          imported_rows: 0,
          failed_rows: 0,
          duplicate_rows: 0,
          failure_message: null,
          user: { id: 1, name: 'Sara Admin' },
          user_name: 'Sara Admin',
          started_at: '2026-11-06T00:00:00Z',
          finished_at: null,
          created_at: '2026-11-06T00:00:00Z',
          updated_at: '2026-11-06T00:00:00Z',
        }
        importsList = [item]
        return HttpResponse.json({ data: item }, { status: 201 })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    expect(await screen.findByRole('heading', { name: 'Ticket Imports' })).toBeInTheDocument()

    const user = userEvent.setup()
    const file = new File(['header\nrow1\nrow2\nrow3'], 'sample.csv', { type: 'text/csv' })

    const input = screen.getByTestId('file-picker-input')
    await user.upload(input, file)

    const uploadBtn = screen.getByRole('button', { name: 'Upload' })
    await user.click(uploadBtn)

    expect(await screen.findByText('Processing')).toBeInTheDocument()
    expect(screen.getByText(/0 of 3 rows/i)).toBeInTheDocument()

    await waitFor(
      () => {
        expect(screen.getByText('Completed')).toBeInTheDocument()
      },
      { timeout: 5000 },
    )
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('displays 409 conflict and 422 validation errors under the picker', async () => {
    let returnStatus = 409
    let responseBody: any = { message: 'Another import is in progress.' }

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        return HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        })
      }),
      http.post('http://api.test/api/v1/admin/imports', () => {
        return HttpResponse.json(responseBody, { status: returnStatus })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    const user = userEvent.setup()
    const input = await screen.findByTestId('file-picker-input')
    const file = new File(['content'], 'test.csv', { type: 'text/csv' })

    // Test 409 Conflict
    await user.upload(input, file)
    await user.click(screen.getByRole('button', { name: 'Upload' }))

    expect(await screen.findByText('Another import is in progress.')).toBeInTheDocument()

    // Test 422 Validation error
    returnStatus = 422
    responseBody = {
      message: 'The file field must be a file of type: csv.',
      errors: { file: ['The file field must be a file of type: csv.'] },
    }

    await user.upload(input, file)
    await user.click(screen.getByRole('button', { name: 'Upload' }))

    expect(await screen.findByText('The file field must be a file of type: csv.')).toBeInTheDocument()
  })

  it('requests template.csv when clicking Download template', async () => {
    let templateRequested = false

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        return HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        })
      }),
      http.get('http://api.test/api/v1/admin/imports/template.csv', () => {
        templateRequested = true
        return new HttpResponse('customer_email,category_name,priority,subject,description\n', {
          headers: {
            'Content-Type': 'text/csv',
            'Content-Disposition': 'attachment; filename="template.csv"',
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    const user = userEvent.setup()
    const downloadLink = await screen.findByRole('button', { name: 'Download template' })
    await user.click(downloadLink)

    expect(templateRequested).toBe(true)
  })

  it('shows Download error report button only for imports with skipped rows and downloads report blob', async () => {
    let reportRequested = false

    const importsWithAndWithoutErrors = [
      {
        id: 101,
        original_filename: 'has-errors.csv',
        status: 'completed',
        total_rows: 10,
        processed_rows: 10,
        imported_rows: 8,
        failed_rows: 1,
        duplicate_rows: 1,
        failure_message: null,
        user: { id: 1, name: 'Sara Admin' },
        user_name: 'Sara Admin',
        started_at: '2026-11-06T00:00:00Z',
        finished_at: '2026-11-06T00:00:05Z',
        created_at: '2026-11-06T00:00:00Z',
        updated_at: '2026-11-06T00:00:05Z',
      },
      {
        id: 102,
        original_filename: 'clean.csv',
        status: 'completed',
        total_rows: 5,
        processed_rows: 5,
        imported_rows: 5,
        failed_rows: 0,
        duplicate_rows: 0,
        failure_message: null,
        user: { id: 1, name: 'Sara Admin' },
        user_name: 'Sara Admin',
        started_at: '2026-11-06T00:01:00Z',
        finished_at: '2026-11-06T00:01:03Z',
        created_at: '2026-11-06T00:01:00Z',
        updated_at: '2026-11-06T00:01:03Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        return HttpResponse.json({
          data: importsWithAndWithoutErrors,
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
        })
      }),
      http.get('http://api.test/api/v1/admin/imports/101/errors.csv', () => {
        reportRequested = true
        return new HttpResponse('line,external_reference,reasons\n2,REF-2,Invalid category\n', {
          headers: {
            'Content-Type': 'text/csv',
            'Content-Disposition': 'attachment; filename="import-101-errors.csv"',
          },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    expect(await screen.findByText('has-errors.csv')).toBeInTheDocument()
    expect(screen.getByText('clean.csv')).toBeInTheDocument()

    // Exactly one "Download error report" button exists (for import 101, not 102)
    const reportButtons = screen.getAllByRole('button', { name: 'Download error report' })
    expect(reportButtons).toHaveLength(1)

    const user = userEvent.setup()
    await user.click(reportButtons[0])

    expect(reportRequested).toBe(true)
  })

  it('displays failure message for a failed import', async () => {
    const failedImport = [
      {
        id: 201,
        original_filename: 'corrupted.csv',
        status: 'failed',
        total_rows: 0,
        processed_rows: 0,
        imported_rows: 0,
        failed_rows: 0,
        duplicate_rows: 0,
        failure_message: 'The file could not be read.',
        user: { id: 1, name: 'Sara Admin' },
        user_name: 'Sara Admin',
        started_at: '2026-11-06T00:00:00Z',
        finished_at: '2026-11-06T00:00:01Z',
        created_at: '2026-11-06T00:00:00Z',
        updated_at: '2026-11-06T00:00:01Z',
      },
    ]

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        return HttpResponse.json({
          data: failedImport,
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    expect(await screen.findByText('corrupted.csv')).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Failed' })).toBeInTheDocument()
    expect(screen.getByText('The file could not be read.')).toBeInTheDocument()
  })

  it('stops polling when no imports are pending or processing', async () => {
    let callCount = 0

    server.use(
      http.get('http://api.test/api/v1/admin/imports', () => {
        callCount++
        return HttpResponse.json({
          data: [
            {
              id: 301,
              original_filename: 'done.csv',
              status: 'completed',
              total_rows: 5,
              processed_rows: 5,
              imported_rows: 5,
              failed_rows: 0,
              duplicate_rows: 0,
              failure_message: null,
              user: { id: 1, name: 'Sara Admin' },
              user_name: 'Sara Admin',
              started_at: '2026-11-06T00:00:00Z',
              finished_at: '2026-11-06T00:00:02Z',
              created_at: '2026-11-06T00:00:00Z',
              updated_at: '2026-11-06T00:00:02Z',
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        })
      }),
    )

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    expect(await screen.findByText('done.csv')).toBeInTheDocument()
    const initialCalls = callCount

    // Wait 2.5s (longer than the 2s poll interval) to ensure polling does not fire
    await new Promise((r) => setTimeout(r, 2500))
    expect(callCount).toBe(initialCalls)
  })

  it('hides Imports nav link and shows Not Authorized page for agents', async () => {
    signIn({ role: 'agent', name: 'Alex Agent' })

    window.history.replaceState(null, '', '/admin/imports')
    render(<Root />)

    // Not authorized page should show
    expect(await screen.findByRole('heading', { name: 'Not authorized' })).toBeInTheDocument()

    // Nav link should not be present
    expect(screen.queryByRole('link', { name: 'Imports' })).not.toBeInTheDocument()
  })
})
