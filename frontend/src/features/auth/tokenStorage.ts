const STORAGE_KEY = 'techzu.token'

export const tokenStorage = {
  getToken(): string | null {
    try {
      return localStorage.getItem(STORAGE_KEY)
    } catch {
      return null
    }
  },

  setToken(token: string): void {
    try {
      localStorage.setItem(STORAGE_KEY, token)
    } catch {
      // Ignore write errors if storage is unavailable or restricted
    }
  },

  clearToken(): void {
    try {
      localStorage.removeItem(STORAGE_KEY)
    } catch {
      // Ignore removal errors
    }
  },
}

