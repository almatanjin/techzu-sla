export type AppConfig = {
  apiUrl: string
}

export class ConfigurationError extends Error {
  override name = 'ConfigurationError'
}

export function loadConfig(env: ImportMetaEnv = import.meta.env): AppConfig {
  const apiUrl = env.VITE_API_URL?.trim()

  if (!apiUrl) {
    throw new ConfigurationError(
      'VITE_API_URL is not set. Copy frontend/.env.example to frontend/.env and restart the dev server.',
    )
  }

  return { apiUrl: apiUrl.replace(/\/+$/, '') }
}
