import { z } from 'zod'

export const GameStatusSchema = z.enum(['submitted', 'published', 'blocked', 'rejected'])
export type GameStatus = z.infer<typeof GameStatusSchema>

export const gameStatusLabels: Record<GameStatus, string> = {
  submitted: 'Em análise',
  published: 'Publicado',
  blocked: 'Bloqueado',
  rejected: 'Rejeitado',
}

/** Zero trust: só aceitamos o caminho exato das thumbnails geradas pelo backend (A4) e URLs https. */
export const GameSchema = z.object({
  id: z.string(),
  slug: z.string(),
  name: z.string(),
  description: z.string(),
  author_name: z.string(),
  thumbnail_url: z.string().regex(/^\/media\/thumbnails\/[0-9a-f]{32}\.webp$/),
  repository_url: z.string().startsWith('https://'),
  default_ref: z.string(),
  status: GameStatusSchema,
  created_at: z.string(),
})
export type Game = z.infer<typeof GameSchema>
