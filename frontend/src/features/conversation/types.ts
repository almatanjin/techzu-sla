import type { Attachment } from '../tickets/types'

export type MessageType = 'reply' | 'internal_note'

export type MessageAuthor = {
  id: number
  name: string
  role: 'customer' | 'agent' | 'admin'
}

export type TicketMessage = {
  id: number
  type: MessageType
  body: string
  author: MessageAuthor
  attachments: Attachment[]
  created_at: string
}

export type PostMessagePayload = {
  type?: MessageType
  body?: string
  files?: File[]
}

