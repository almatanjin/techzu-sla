export type UserRole = 'admin' | 'agent' | 'customer'

export type User = {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
  created_at?: string
}

export type AuthTokenResponse = {
  token: string
  token_type: string
  expires_at: string
  user: User
}

export type RegisterPayload = {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export type LoginPayload = {
  email: string
  password: string
}

export type ForgotPasswordPayload = {
  email: string
}

export type ResetPasswordPayload = {
  email: string
  token: string
  password: string
  password_confirmation: string
}

