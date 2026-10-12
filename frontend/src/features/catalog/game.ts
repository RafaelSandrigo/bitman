import { z } from 'zod'
import { apiGet } from '../../shared/api/http'

export const GameStatusSchema = z.enum(['submitted', 'published', 'blocked', 'rejected'])
export type GameStatus = z.infer<typeof GameStatusSchema>

export const gameStatusLabels: Record<GameStatus, string> = {
  submitted: 'Em análise',
  published: 'Publicado',
  blocked: 'Bloqueado',
  rejected: 'Rejeitado',
}

/** Zero trust: só o caminho exato das thumbnails geradas pelo backend (A4). */
const Thumbnail = z.string().regex(/^\/media\/thumbnails\/[0-9a-f]{32}\.webp$/)

/** Jogo visto pelo dono e pelo admin. Zero trust: thumbnail no caminho exato e URL https. */
export const GameSchema = z.object({
  id: z.string(),
  slug: z.string(),
  name: z.string(),
  description: z.string(),
  author_name: z.string(),
  thumbnail_url: Thumbnail,
  repository_url: z.string().startsWith('https://'),
  default_ref: z.string(),
  status: GameStatusSchema,
  published_version_id: z.string().nullable(),
  first_published_at: z.string().nullable(),
  /** Visível para o dono e o admin. */
  block_reason: z.string().nullable(),
  created_at: z.string(),
})
export type Game = z.infer<typeof GameSchema>

/** Jogo do catálogo público (I9). A `play_url` só é usada se passar em `isPlayUrl`. */
export const PublicGameSchema = z.object({
  slug: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/),
  name: z.string(),
  description: z.string(),
  author_name: z.string(),
  thumbnail_url: Thumbnail,
  version_number: z.number().int().positive(),
  published_at: z.string(),
  play_url: z.string(),
})
export type PublicGame = z.infer<typeof PublicGameSchema>

export async function listPublishedGames() {
  return (await apiGet('/api/v1/games', z.object({ data: z.array(PublicGameSchema) }))).data
}

export async function getPublishedGame(slug: string) {
  return (await apiGet(`/api/v1/games/${encodeURIComponent(slug)}`, z.object({ data: PublicGameSchema }))).data
}
