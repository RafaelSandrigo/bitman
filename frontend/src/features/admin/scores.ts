import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'

export const AdminScoreSchema = z.object({
  id: z.string(),
  game: z.object({ id: z.string(), slug: z.string(), name: z.string() }),
  player: z.object({ id: z.string(), name: z.string() }),
  version_number: z.number().int().positive(),
  value: z.number().int(),
  session_seconds: z.number().int().nullable(),
  flagged: z.boolean(),
  flag_reason: z.string().nullable(),
  hidden: z.object({ by: z.string().nullable(), at: z.string().nullable(), reason: z.string().nullable() }).nullable(),
  created_at: z.string(),
})
export type AdminScore = z.infer<typeof AdminScoreSchema>

export const scoreFilters = ['flagged', 'hidden', 'all'] as const
export type ScoreFilter = (typeof scoreFilters)[number]

const ScorePageSchema = z.object({
  data: z.array(AdminScoreSchema),
  meta: z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }),
})

export function listScores(filter: ScoreFilter, page: number) {
  return apiGet(`/api/v1/admin/scores?filter=${filter}&page=${page}`, ScorePageSchema)
}

const ScoreEnvelope = z.object({ data: AdminScoreSchema })

/** S2: tira o score do ranking, com motivo (auditado). Ele continua registrado. */
export function hideScore(id: string, reason: string) {
  return apiSend('POST', `/api/v1/admin/scores/${encodeURIComponent(id)}/hide`, ScoreEnvelope, { reason })
}

export function restoreScore(id: string) {
  return apiSend('POST', `/api/v1/admin/scores/${encodeURIComponent(id)}/restore`, ScoreEnvelope)
}
