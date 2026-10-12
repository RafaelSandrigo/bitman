import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'
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
