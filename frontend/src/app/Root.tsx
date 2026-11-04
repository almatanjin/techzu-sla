import { ConfigurationError, loadConfig, type AppConfig } from '../shared/config'
import { App } from './App'
import { ConfigurationErrorPage } from './ConfigurationErrorPage'

export function Root() {
  let config: AppConfig

  try {
    config = loadConfig()
  } catch (error) {
    if (error instanceof ConfigurationError) {
      return <ConfigurationErrorPage message={error.message} />
    }
    throw error
  }

  return <App config={config} />
}
