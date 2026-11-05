export const ALLOWED_EXTENSIONS = [
  'pdf',
  'png',
  'jpg',
  'jpeg',
  'gif',
  'webp',
  'txt',
  'csv',
  'log',
  'docx',
  'xlsx',
  'zip',
] as const

export const MAX_ATTACHMENT_FILES = 5
export const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024 // 10 MB

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`
  }
  const kb = Math.round(bytes / 1024)
  if (kb < 1024) {
    return `${kb} KB`
  }
  const mb = (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '')
  return `${mb} MB`
}

export function validateAttachmentFiles(files: File[]): string | null {
  if (files.length > MAX_ATTACHMENT_FILES) {
    return `You can attach up to ${MAX_ATTACHMENT_FILES} files.`
  }

  const allowedExtList = ALLOWED_EXTENSIONS.join(', ')

  for (const file of files) {
    const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
    if (!ALLOWED_EXTENSIONS.includes(ext as (typeof ALLOWED_EXTENSIONS)[number])) {
      return `${file.name} must be one of these types: ${allowedExtList}.`
    }
    if (file.size > MAX_ATTACHMENT_BYTES) {
      return `${file.name} must not be larger than 10 MB.`
    }
  }

  return null
}

