import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'
import { versionStatuses } from '../admin/versions'
import { GameSchema } from '../catalog/game'

export type SubmissionInput = {
  name: string
  description: string
  author_name: string
  repository_url: string
  ref: string
  thumbnail: File
}

export async function submitGame(input: SubmissionInput) {
  const body = new FormData()
  for (const [key, value] of Object.entries(input)) body.append(key, value)
  return (await apiSend('POST', '/api/v1/games', z.object({ data: GameSchema }), body)).data
}

export async function listMyGames() {
  return (await apiGet('/api/v1/me/games', z.object({ data: z.array(GameSchema) }))).data
}

/** A versão mais recente de cada jogo, como o dono a vê: estado e, quando parou, o motivo. */
export const MyVersionSchema = z.object({
  id: z.string(),
  game_id: z.string(),
  number: z.number().int().positive(),
  status: z.enum(versionStatuses),
  in_progress: z.boolean(),
  requested_ref: z.string(),
  commit_sha: z.string().nullable(),
  rejection_reason: z.string().nullable(),
  fetch_failure: z.object({ code: z.string(), message: z.string() }).nullable(),
  violations: z.array(z.object({ code: z.string(), message: z.string(), path: z.string().nullable() })),
  created_at: z.string(),
  updated_at: z.string(),
})
export type MyVersion = z.infer<typeof MyVersionSchema>

export async function listMyVersions() {
  return (await apiGet('/api/v1/me/versions', z.object({ data: z.array(MyVersionSchema) }))).data
}

/** Reenvio: nova versão pela URL do jogo e a ref dele (ou outra, só para esta versão: M1), de volta à autorização do admin. */
export async function resubmitGame(gameId: string, ref?: string) {
  const body = ref ? { ref } : {}
  return (await apiSend('POST', `/api/v1/me/games/${encodeURIComponent(gameId)}/versions`, z.object({ data: MyVersionSchema }), body)).data
}

const HistoryPageSchema = z.object({
  data: z.array(MyVersionSchema),
  meta: z.object({
    game: z.object({ id: z.string(), name: z.string(), slug: z.string() }),
    current_page: z.number(),
    last_page: z.number(),
    total: z.number(),
  }),
})

/** M2: todas as versões de um jogo do dono. */
export function listMyGameVersions(gameId: string, page: number) {
  return apiGet(`/api/v1/me/games/${encodeURIComponent(gameId)}/versions?page=${page}`, HistoryPageSchema)
}
