import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { Badge, type BadgeVariant } from '../../shared/components/Badge'
import { FilePicker } from '../../shared/components/FilePicker'
import { Pagination } from '../../shared/components/Pagination'
import {
  downloadErrorReport,
  downloadTemplate,
  listImports,
  uploadImport,
} from './api'
import type { TicketImport, TicketImportListResponse, TicketImportStatus } from './types'

const statusVariantMap: Record<TicketImportStatus, BadgeVariant> = {
  pending: 'neutral',
  processing: 'info',
  completed: 'success',
  failed: 'danger',
}

const statusLabelMap: Record<TicketImportStatus, string> = {
  pending: 'Pending',
  processing: 'Processing',
  completed: 'Completed',
  failed: 'Failed',
}

export function ImportsPage() {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()

  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [isUploading, setIsUploading] = useState(false)
  const [downloadingReportId, setDownloadingReportId] = useState<number | null>(null)
  const [isDownloadingTemplate, setIsDownloadingTemplate] = useState(false)

  const page = Number(searchParams.get('page') ?? '1')

  const { data, isLoading, isError, refetch } = useQuery<TicketImportListResponse>({
    queryKey: ['admin', 'imports', page],
    queryFn: () => listImports(apiClient, { page }),
    refetchInterval: (query) => {
      const items = query.state.data?.data ?? []
      const hasActive = items.some(
        (item) => item.status === 'pending' || item.status === 'processing',
      )
      return hasActive ? 2000 : false
    },
  })

  const handleUpload = async () => {
    if (!selectedFile) return
    try {
      setIsUploading(true)
      setUploadError(null)
      await uploadImport(apiClient, selectedFile)
      setSelectedFile(null)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'imports'] })
    } catch (err: unknown) {
      if (axios.isAxiosError(err)) {
        if (err.response?.status === 409) {
          const msg = (err.response.data as { message?: string })?.message
          setUploadError(msg || 'Another import is in progress.')
        } else if (err.response?.status === 422) {
          const errData = err.response.data as {
            message?: string
            errors?: { file?: string[] }
          }
          const fileErr = errData.errors?.file?.[0] || errData.message || 'Validation failed'
          setUploadError(fileErr)
        } else {
          const msg = (err.response?.data as { message?: string })?.message
          setUploadError(msg || 'Upload failed.')
        }
      } else {
        setUploadError('An unexpected error occurred.')
      }
    } finally {
      setIsUploading(false)
    }
  }

  const handleDownloadTemplate = async () => {
    try {
      setIsDownloadingTemplate(true)
      await downloadTemplate(apiClient)
    } catch (err) {
      console.error('Failed to download template', err)
    } finally {
      setIsDownloadingTemplate(false)
    }
  }

  const handleDownloadReport = async (importId: number) => {
    try {
      setDownloadingReportId(importId)
      await downloadErrorReport(apiClient, importId)
    } catch (err) {
      console.error('Failed to download error report', err)
    } finally {
      setDownloadingReportId(null)
    }
  }

  const handlePageChange = (newPage: number) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (newPage > 1) {
        next.set('page', String(newPage))
      } else {
        next.delete('page')
      }
      return next
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-fg">Ticket Imports</h1>
          <p className="mt-1 text-sm text-fg-muted">
            Import tickets from CSV files in bulk with automatic SLA calculations.
          </p>
        </div>
        <button
          type="button"
          onClick={handleDownloadTemplate}
          disabled={isDownloadingTemplate}
          className="link text-sm font-medium"
        >
          {isDownloadingTemplate ? 'Downloading template...' : 'Download template'}
        </button>
      </div>

      {/* Upload Card */}
      <div className="card p-6 space-y-4">
        <h2 className="text-base font-semibold text-fg">Upload CSV</h2>
        <div>
          <FilePicker
            files={selectedFile ? [selectedFile] : []}
            onChange={(files) => {
              setSelectedFile(files[files.length - 1] ?? null)
              setUploadError(null)
            }}
            disabled={isUploading}
          />
          {uploadError && (
            <p role="alert" className="mt-2 text-xs font-medium text-danger">
              {uploadError}
            </p>
          )}
        </div>

        <div className="flex justify-end">
          <button
            type="button"
            disabled={!selectedFile || isUploading}
            onClick={handleUpload}
            className="btn btn-primary btn-sm"
          >
            {isUploading ? 'Uploading...' : 'Upload'}
          </button>
        </div>
      </div>

      {/* History table */}
      <div>
        <h2 className="text-lg font-semibold text-fg mb-3">Import History</h2>

        {isLoading && (
          <div className="card p-12 text-center text-fg-subtle">Loading imports...</div>
        )}

        {isError && (
          <div role="alert" className="alert alert-danger p-6 text-center">
            <p>Failed to load import history.</p>
            <button
              type="button"
              onClick={() => void refetch()}
              className="mt-3 btn btn-danger btn-sm"
            >
              Retry
            </button>
          </div>
        )}

        {!isLoading && !isError && data && (
          <div className="overflow-hidden card">
            {data.data.length === 0 ? (
              <div className="p-12 text-center text-fg-subtle">No imports found.</div>
            ) : (
              <>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-border text-left text-sm">
                    <thead className="bg-surface-muted text-fg-secondary">
                      <tr>
                        <th className="px-4 py-3 font-semibold">File name</th>
                        <th className="px-4 py-3 font-semibold">Admin</th>
                        <th className="px-4 py-3 font-semibold">Started</th>
                        <th className="px-4 py-3 font-semibold">Status</th>
                        <th className="px-4 py-3 font-semibold">Progress</th>
                        <th className="px-4 py-3 font-semibold">Imported</th>
                        <th className="px-4 py-3 font-semibold">Failed</th>
                        <th className="px-4 py-3 font-semibold">Duplicate</th>
                        <th className="px-4 py-3 font-semibold text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border bg-surface text-fg">
                      {data.data.map((item: TicketImport) => {
                        const total = item.total_rows
                        const processed = item.processed_rows
                        const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0
                        const hasSkipped = item.failed_rows > 0 || item.duplicate_rows > 0

                        return (
                          <tr key={item.id} className="hover:bg-surface-muted">
                            <td className="px-4 py-3 font-medium">
                              <div>{item.original_filename}</div>
                              {item.failure_message && (
                                <p role="alert" className="text-xs text-danger mt-1">
                                  {item.failure_message}
                                </p>
                              )}
                            </td>
                            <td className="px-4 py-3 text-fg-muted">
                              {item.user_name ?? item.user?.name ?? '-'}
                            </td>
                            <td className="px-4 py-3 text-fg-muted whitespace-nowrap">
                              {item.started_at
                                ? new Date(item.started_at).toLocaleString()
                                : new Date(item.created_at).toLocaleString()}
                            </td>
                            <td className="px-4 py-3">
                              <Badge variant={statusVariantMap[item.status]}>
                                {statusLabelMap[item.status]}
                              </Badge>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                              <div className="text-xs text-fg-muted mb-1">
                                {processed} of {total} rows
                              </div>
                              <div className="w-28 bg-surface-muted rounded-full h-1.5 overflow-hidden">
                                <div
                                  className="bg-primary h-1.5 rounded-full transition-all duration-300"
                                  style={{ width: `${percent}%` }}
                                />
                              </div>
                            </td>
                            <td className="px-4 py-3">{item.imported_rows}</td>
                            <td className="px-4 py-3">{item.failed_rows}</td>
                            <td className="px-4 py-3">{item.duplicate_rows}</td>
                            <td className="px-4 py-3 text-right whitespace-nowrap">
                              {hasSkipped && item.status !== 'pending' && item.status !== 'processing' && (
                                <button
                                  type="button"
                                  onClick={() => handleDownloadReport(item.id)}
                                  disabled={downloadingReportId === item.id}
                                  className="btn btn-outline btn-xs"
                                >
                                  {downloadingReportId === item.id
                                    ? 'Downloading...'
                                    : 'Download error report'}
                                </button>
                              )}
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>

                {data.meta.last_page > 1 && (
                  <div className="p-4 border-t border-border">
                    <Pagination meta={data.meta} onPageChange={handlePageChange} />
                  </div>
                )}
              </>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
