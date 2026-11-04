import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Outlet, useNavigate } from 'react-router'
import { useApiClient } from '../../shared/api/apiClientContext'
import { registerUnauthorizedHandler } from '../../shared/api/client'
import * as authApi from './api'
import { AuthContext, type AuthContextValue, type AuthStatus } from './authContextDef'
import { markSessionEnded } from './sessionEndedTracker'
import { tokenStorage } from './tokenStorage'
import type { LoginPayload, RegisterPayload, User } from './types'

export function AuthProvider({ children }: { children?: ReactNode }) {
  const apiClient = useApiClient()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [token, setTokenState] = useState<string | null>(() => tokenStorage.getToken())

  const {
    data: user,
    isLoading,
    isError,
  } = useQuery<User>({
    queryKey: ['auth', 'me'],
    queryFn: () => authApi.getMe(apiClient),
    enabled: Boolean(token),
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  const handleUnauthorized = useCallback(() => {
    markSessionEnded()
    tokenStorage.clearToken()
    setTokenState(null)
    queryClient.setQueryData(['auth', 'me'], null)
    navigate('/login', {
      replace: true,
      state: { sessionEnded: true },
    })
  }, [navigate, queryClient])

  useEffect(() => {
    const unregister = registerUnauthorizedHandler(handleUnauthorized)
    return unregister
  }, [handleUnauthorized])

  useEffect(() => {
    const handleStorageChange = (event: StorageEvent) => {
      if (event.key === 'techzu.token') {
        if (!event.newValue) {
          setTokenState(null)
          queryClient.setQueryData(['auth', 'me'], null)
          navigate('/login', { replace: true, state: { sessionEnded: true } })
        } else {
          setTokenState(event.newValue)
          queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
        }
      }
    }

    window.addEventListener('storage', handleStorageChange)
    return () => window.removeEventListener('storage', handleStorageChange)
  }, [navigate, queryClient])

  const login = useCallback(
    async (payload: LoginPayload): Promise<void> => {
      const response = await authApi.login(apiClient, payload)
      tokenStorage.setToken(response.token)
      setTokenState(response.token)
      queryClient.setQueryData(['auth', 'me'], response.user)
    },
    [apiClient, queryClient],
  )

  const register = useCallback(
    async (payload: RegisterPayload): Promise<void> => {
      const response = await authApi.register(apiClient, payload)
      tokenStorage.setToken(response.token)
      setTokenState(response.token)
      queryClient.setQueryData(['auth', 'me'], response.user)
    },
    [apiClient, queryClient],
  )

  const logout = useCallback(async (): Promise<void> => {
    try {
      await authApi.logout(apiClient)
    } finally {
      tokenStorage.clearToken()
      setTokenState(null)
      queryClient.clear()
      navigate('/login', { replace: true })
    }
  }, [apiClient, navigate, queryClient])

  const status: AuthStatus = useMemo(() => {
    if (!token) {
      return 'guest'
    }
    if (isLoading) {
      return 'loading'
    }
    if (isError || !user) {
      return 'guest'
    }
    return 'authenticated'
  }, [token, isLoading, isError, user])

  const effectiveUser = status === 'authenticated' ? user ?? null : null

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user: effectiveUser,
      login,
      register,
      logout,
    }),
    [status, effectiveUser, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children ?? <Outlet />}</AuthContext.Provider>
}
