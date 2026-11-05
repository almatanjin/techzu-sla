import { useRef, type ChangeEvent } from 'react'
import { formatFileSize, validateAttachmentFiles } from '../attachments'

type FilePickerProps = {
  files: File[]
  onChange: (files: File[]) => void
  error?: string | null
  disabled?: boolean
}

export function FilePicker({ files, onChange, error, disabled = false }: FilePickerProps) {
  const inputRef = useRef<HTMLInputElement | null>(null)

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files) return

    const selectedList = Array.from(e.target.files)
    const combined = [...files, ...selectedList]

    const validationError = validateAttachmentFiles(combined)
    if (validationError) {
      // Still notify parent or let parent see error
      // Note: we can pass combined or keep valid
    }

    onChange(combined)
    // Reset file input value so re-selecting the same file works
    if (inputRef.current) {
      inputRef.current.value = ''
    }
  }

  const handleRemove = (indexToRemove: number) => {
    const updated = files.filter((_, idx) => idx !== indexToRemove)
    onChange(updated)
  }

  return (
    <div className="space-y-2">
      <input
        ref={inputRef}
        type="file"
        multiple
        disabled={disabled}
        onChange={handleFileChange}
        className="sr-only"
        data-testid="file-picker-input"
        aria-label="Attach files"
      />

      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          disabled={disabled}
          onClick={() => inputRef.current?.click()}
          className="btn btn-sm btn-outline inline-flex items-center gap-1.5"
        >
          <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="2"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.373L8.557 18.315a1.5 1.5 0 1 1-2.122-2.122L16.5 6.139"
            />
          </svg>
          <span>Attach files</span>
        </button>

        {error && (
          <span role="alert" className="text-xs font-medium text-danger">
            {error}
          </span>
        )}
      </div>

      {files.length > 0 && (
        <ul className="flex flex-wrap gap-2 pt-1">
          {files.map((file, idx) => (
            <li
              key={`${file.name}-${idx}`}
              className="inline-flex items-center gap-2 rounded-md border border-border bg-surface-subtle px-2.5 py-1 text-xs text-fg"
            >
              <span>{`${file.name} (${formatFileSize(file.size)})`}</span>
              <button
                type="button"
                onClick={() => handleRemove(idx)}
                className="text-fg-subtle hover:text-danger focus:outline-none"
                aria-label={`Remove ${file.name}`}
              >
                &times;
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

