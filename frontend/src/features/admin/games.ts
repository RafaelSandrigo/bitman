import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'
import { GameSchema } from '../catalog/game'

const AdminGameSchema = z.object({
  data: GameSchema.extend({ owner: z.object({ id: z.string(), name: z.string(), email: z.string() }) }),
  /** O diretório da versão publicada existe agora na origem de jogos (J6). */
  meta: z.object({ is_served: z.boolean() }),
})
export type AdminGame = z.infer<typeof AdminGameSchema>

export function getAdminGame(id: string) {
  return apiGet(`/api/v1/admin/games/${encodeURIComponent(id)}`, AdminGameSchema)
}

const GameEnvelope = z.object({ data: GameSchema })

/** J6: tira o jogo do ar na hora, com motivo. */
export function blockGame(id: string, reason: string) {
  return apiSend('POST', `/api/v1/admin/games/${encodeURIComponent(id)}/block`, GameEnvelope, { reason })
}

export function unblockGame(id: string) {
  return apiSend('POST', `/api/v1/admin/games/${encodeURIComponent(id)}/unblock`, GameEnvelope)
}
