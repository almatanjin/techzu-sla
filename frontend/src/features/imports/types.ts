import type { PaginationMeta } from '../../shared/api/types'

export type TicketImportStatus = 'pending' | 'processing' | 'completed' | 'failed'

export type TicketImport = {
  id: number
  original_filename: string
  status: TicketImportStatus
  total_rows: number
  processed_rows: number
  imported_rows: number
  failed_rows: number
  duplicate_rows: number
  failure_message: string | null
  user: {
    id: number
    name: string | null
  } | null
  user_name: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string
  updated_at: string
}

export type TicketImportListResponse = {
  data: TicketImport[]
  meta: PaginationMeta
}

