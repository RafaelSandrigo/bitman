import { z } from 'zod'
import { ApiError, apiGet, apiSend, NoContent } from '../../shared/api/http'

export const RoleSchema = z.enum(['player', 'developer', 'admin'])
export type Role = z.infer<typeof RoleSchema>

export const UserSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string(),
  email_verified: z.boolean(),
  roles: z.array(RoleSchema),
  created_at: z.string(),
})
export type User = z.infer<typeof UserSchema>

export const CurrentUserSchema = UserSchema.extend({ permissions: z.array(z.string()) })
export type CurrentUser = z.infer<typeof CurrentUserSchema>

export const Permission = {
  SubmitGames: 'games.submit',
  ManageRoles: 'users.manage-roles',
  ViewAudit: 'audit.view',
} as const
export type Permission = (typeof Permission)[keyof typeof Permission]

const MessageSchema = z.object({ message: z.string() })

/** Usuário da sessão, ou `null` para visitante. */
export async function fetchCurrentUser(): Promise<CurrentUser | null> {
  try {
    return (await apiGet('/api/v1/me', z.object({ data: CurrentUserSchema }))).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}

export function login(input: { email: string; password: string }) {
  return apiSend('POST', '/api/v1/auth/login', NoContent, input)
}

export function logout() {
  return apiSend('POST', '/api/v1/auth/logout', NoContent)
}

export function register(input: { name: string; email: string; password: string; password_confirmation: string }) {
  return apiSend('POST', '/api/v1/auth/register', NoContent, input)
}

export async function requestPasswordReset(email: string): Promise<string> {
  return (await apiSend('POST', '/api/v1/auth/forgot-password', MessageSchema, { email })).message
}

export function resetPassword(input: { token: string; email: string; password: string; password_confirmation: string }) {
  return apiSend('POST', '/api/v1/auth/reset-password', NoContent, input)
}

export function resendVerification() {
  return apiSend('POST', '/api/v1/auth/email/verification-notification', NoContent)
}

/**
 * O e-mail traz a URL assinada do backend. Só aceitamos exatamente esse formato,
 * para que o parâmetro da página não vire uma chamada arbitrária à API.
 */
const VERIFICATION_LINK = /^\/api\/v1\/auth\/email\/verify\/[0-9a-hjkmnp-tv-z]{26}\/[0-9a-f]{40}\?expires=\d+&signature=[0-9a-f]{64}$/i

export function isVerificationLink(link: string): boolean {
  return VERIFICATION_LINK.test(link)
}

export function verifyEmail(link: string) {
  if (!isVerificationLink(link)) return Promise.reject(new ApiError('Link de verificação inválido', 0, null))
  return apiGet(link, NoContent)
}
