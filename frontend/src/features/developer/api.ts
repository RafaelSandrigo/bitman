import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'

/** M18: pedido para ser developer. Nome, GitHub e apresentação são texto do usuário: só exibidos como texto. */
export const DeveloperRequestSchema = z.object({
  id: z.string(),
  name: z.string(),
  github_url: z.string(),
  bio: z.string(),
  status: z.enum(['pending', 'approved', 'rejected']),
  decided_by: z.string().nullable(),
  decided_at: z.string().nullable(),
  decision_reason: z.string().nullable(),
  created_at: z.string(),
})
export type DeveloperRequest = z.infer<typeof DeveloperRequestSchema>

export type DeveloperApplication = { name: string; github_url: string; bio: string }

export async function getMyDeveloperRequest() {
  return (await apiGet('/api/v1/me/developer-request', z.object({ data: DeveloperRequestSchema.nullable() }))).data
}

export async function requestDeveloperRole(application: DeveloperApplication) {
  return (await apiSend('POST', '/api/v1/me/developer-request', z.object({ data: DeveloperRequestSchema }), application)).data
}

/** Só `https://github.com/{usuário}`; o servidor confere de novo. */
export const githubProfilePattern = /^https:\/\/github\.com\/[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}\/?$/i

/** O link vem do servidor já normalizado, mas é texto do usuário: só vira href se for um perfil do GitHub. */
export function isGithubProfile(url: string): boolean {
  return githubProfilePattern.test(url)
}
