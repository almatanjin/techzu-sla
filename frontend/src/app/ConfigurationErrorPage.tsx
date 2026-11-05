type Props = {
  message: string
}

export function ConfigurationErrorPage({ message }: Props) {
  return (
    <main className="mx-auto max-w-2xl px-4 py-12">
      <div role="alert" className="alert alert-danger p-4">
        <h1 className="font-semibold">The app is not configured</h1>
        <p className="mt-1 text-sm break-words">{message}</p>
      </div>
    </main>
  )
}
