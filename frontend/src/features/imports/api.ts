import type { AxiosInstance } from 'axios'
import type { TicketImport, TicketImportListResponse } from './types'

export async function listImports(
  client: AxiosInstance,
  params?: { page?: number },
): Promise<TicketImportListResponse> {
  const response = await client.get<TicketImportListResponse>('admin/imports', { params })
  return response.data
}

export async function getImport(
  client: AxiosInstance,
  id: number,
): Promise<TicketImport> {
  const response = await client.get<TicketImport | { data: TicketImport }>(`admin/imports/${id}`)
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function uploadImport(
  client: AxiosInstance,
  file: File,
): Promise<TicketImport> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await client.post<TicketImport | { data: TicketImport }>(
    'admin/imports',
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    },
  )
  const resData = response.data
  return 'data' in resData ? resData.data : resData
}

export async function downloadErrorReport(
  client: AxiosInstance,
  importId: number,
): Promise<void> {
  const response = await client.get(`admin/imports/${importId}/errors.csv`, {
    responseType: 'blob',
  })
  const blob = new Blob([response.data as BlobPart])
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `import-${importId}-errors.csv`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export async function downloadTemplate(client: AxiosInstance): Promise<void> {
  const response = await client.get('admin/imports/template.csv', {
    responseType: 'blob',
  })
  const blob = new Blob([response.data as BlobPart])
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', 'template.csv')
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

