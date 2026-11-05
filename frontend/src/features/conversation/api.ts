import type { AxiosInstance } from 'axios'
import type { Attachment } from '../tickets/types'
import type { PostMessagePayload, TicketMessage } from './types'

export type MessagesResponse = {
  data: TicketMessage[]
  links?: {
    next?: string | null
    prev?: string | null
  }
  meta?: {
    next_cursor?: string | null
    prev_cursor?: string | null
  }
}

export async function fetchMessages(
  client: AxiosInstance,
  ticketNumber: string,
  cursor?: string | null,
): Promise<MessagesResponse> {
  const url = cursor
    ? `tickets/${ticketNumber}/messages?cursor=${encodeURIComponent(cursor)}`
    : `tickets/${ticketNumber}/messages`

  const response = await client.get<MessagesResponse>(url)
  return response.data
}

export async function postMessage(
  client: AxiosInstance,
  ticketNumber: string,
  payload: PostMessagePayload,
): Promise<TicketMessage> {
  const formData = new FormData()
  if (payload.type) {
    formData.append('type', payload.type)
  }
  if (payload.body !== undefined) {
    formData.append('body', payload.body)
  }
  if (payload.files && payload.files.length > 0) {
    payload.files.forEach((file) => {
      formData.append('attachments[]', file, file.name)
    })
  }

  const response = await client.post<TicketMessage | { data: TicketMessage }>(
    `tickets/${ticketNumber}/messages`,
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    },
  )

  const data = response.data
  if (data && typeof data === 'object' && 'data' in data && !Array.isArray(data)) {
    return (data as { data: TicketMessage }).data
  }
  return data as TicketMessage
}

export async function downloadAttachment(
  client: AxiosInstance,
  attachment: Pick<Attachment, 'id' | 'name'>,
): Promise<void> {
  const response = await client.get(`attachments/${attachment.id}/download`, {
    responseType: 'blob',
  })
  const blob = new Blob([response.data as BlobPart])
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', attachment.name)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
