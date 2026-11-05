import { useState } from 'react'
import { useApiClient } from '../../shared/api/apiClientContext'
import { formatFileSize } from '../../shared/attachments'
import type { Attachment } from '../tickets/types'
import { downloadAttachment } from './api'

type AttachmentListProps = {
  attachments: Attachment[]
}

export function AttachmentList({ attachments }: AttachmentListProps) {
  const apiClient = useApiClient()
  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  if (!attachments || attachments.length === 0) {
    return null
  }

  const handleDownload = async (attachment: Attachment) => {
    try {
      setDownloadingId(attachment.id)
      await downloadAttachment(apiClient, attachment)
    } finally {
      setDownloadingId(null)
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2 pt-2">
      {attachments.map((attachment) => (
        <button
          key={attachment.id}
          type="button"
          disabled={downloadingId === attachment.id}
          onClick={() => void handleDownload(attachment)}
          className="btn btn-sm btn-outline inline-flex items-center gap-1.5 text-xs"
          title={`Download ${attachment.name}`}
        >
          <span>{`${attachment.name} (${formatFileSize(attachment.size_bytes)})`}</span>
        </button>
      ))}
    </div>
  )
}

