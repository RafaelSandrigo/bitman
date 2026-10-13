import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'

/** O token só vive na memória desta página: nunca vai para storage, URL nem para o jogo. */
export const GameSessionSchema = z.object({
  id: z.string(),
  token: z.string().regex(/^[0-9a-f]{64}$/),
  play_url: z.string(),
  started_at: z.string(),
  expires_at: z.string(),
  min_seconds: z.number().int().nonnegative(),
})
export type GameSession = z.infer<typeof GameSessionSchema>

export const ScoreResultSchema = z.object({
  value: z.number().int(),
  position: z.number().int().positive().nullable(),
  personal_best: z.boolean(),
  previous_best: z.number().int().nullable(),
})
export type ScoreResult = z.infer<typeof ScoreResultSchema>

export const LeaderboardEntrySchema = z.object({
  position: z.number().int().positive(),
  player_name: z.string(),
  value: z.number().int(),
  version_number: z.number().int().positive(),
  achieved_at: z.string(),
  is_me: z.boolean(),
})
export type LeaderboardEntry = z.infer<typeof LeaderboardEntrySchema>

export const MyScoreSchema = z.object({
  id: z.string(),
  game: z.object({ slug: z.string(), name: z.string() }),
  version_number: z.number().int().positive(),
  value: z.number().int(),
  hidden: z.boolean(),
  created_at: z.string(),
})
export type MyScore = z.infer<typeof MyScoreSchema>

const PageMetaSchema = z.object({ current_page: z.number(), last_page: z.number(), total: z.number() })

export async function startSession(slug: string) {
  return (await apiSend('POST', `/api/v1/games/${encodeURIComponent(slug)}/sessions`, z.object({ data: GameSessionSchema }))).data
}

export async function submitScore(session: GameSession, score: number) {
  return (
    await apiSend('POST', `/api/v1/game-sessions/${encodeURIComponent(session.id)}/score`, z.object({ data: ScoreResultSchema }), {
      score,
      token: session.token,
    })
  ).data
}

export async function getLeaderboard(slug: string) {
  return (await apiGet(`/api/v1/games/${encodeURIComponent(slug)}/leaderboard`, z.object({ data: z.array(LeaderboardEntrySchema) }))).data
}

export function listMyScores(page: number) {
  return apiGet(`/api/v1/me/scores?page=${page}`, z.object({ data: z.array(MyScoreSchema), meta: PageMetaSchema }))
}
