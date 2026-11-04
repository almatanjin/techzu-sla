import type { AxiosInstance } from 'axios'
import type {
  AuthTokenResponse,
  ForgotPasswordPayload,
  LoginPayload,
  RegisterPayload,
  ResetPasswordPayload,
  User,
} from './types'

export async function register(
  client: AxiosInstance,
  payload: RegisterPayload,
): Promise<AuthTokenResponse> {
  const response = await client.post<AuthTokenResponse>('auth/register', payload)
  return response.data
}

export async function login(
  client: AxiosInstance,
  payload: LoginPayload,
): Promise<AuthTokenResponse> {
  const response = await client.post<AuthTokenResponse>('auth/login', payload)
  return response.data
}

export async function getMe(client: AxiosInstance): Promise<User> {
  const response = await client.get<User>('auth/me')
  return response.data
}

export async function logout(client: AxiosInstance): Promise<void> {
  await client.post('auth/logout')
}

export async function forgotPassword(
  client: AxiosInstance,
  payload: ForgotPasswordPayload,
): Promise<{ message: string }> {
  const response = await client.post<{ message: string }>('auth/forgot-password', payload)
  return response.data
}

export async function resetPassword(
  client: AxiosInstance,
  payload: ResetPasswordPayload,
): Promise<{ message: string }> {
  const response = await client.post<{ message: string }>('auth/reset-password', payload)
  return response.data
}

