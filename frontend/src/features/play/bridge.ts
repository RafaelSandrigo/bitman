import { z } from 'zod'

/**
 * Bridge `postMessage` entre o jogo (iframe da origem isolada) e o hub (J4, protocolo-do-jogo.md).
 * Zero trust: só mensagens do iframe desta partida, com origem opaca, no formato do protocolo.
 * O jogo nunca recebe nada do hub: a comunicação é só dele para cá.
 */

/** Inteiro em 0..Number.MAX_SAFE_INTEGER; nunca arredondado (S2). */
export const ScoreValueSchema = z.number().refine((value) => Number.isSafeInteger(value) && value >= 0)

const EnvelopeSchema = z.object({ source: z.literal('bitman'), version: z.literal(1), type: z.string() })

export type GameEvent = { kind: 'ready' } | { kind: 'game-over'; score: number } | { kind: 'invalid-score' }

/** Mensagem fora do formato → `null` (ignorada em silêncio). `game-over` com score inválido é rejeitado. */
export function parseGameMessage(data: unknown): GameEvent | null {
  const envelope = EnvelopeSchema.safeParse(data)
  if (!envelope.success) return null

  if (envelope.data.type === 'ready') return { kind: 'ready' }
  if (envelope.data.type === 'game-over') {
    const score = ScoreValueSchema.safeParse((data as { score?: unknown }).score)
    return score.success ? { kind: 'game-over', score: score.data } : { kind: 'invalid-score' }
  }
  return null
}

/**
 * Escuta as mensagens do iframe. Só aceita `event.source === iframe.contentWindow` com
 * `event.origin === 'null'` (sandbox sem allow-same-origin). Apenas o primeiro `game-over`
 * (válido ou não) é entregue: depois dele, a partida acabou.
 */
export function listenToGame(frame: () => HTMLIFrameElement | null, onEvent: (event: GameEvent) => void): () => void {
  let over = false

  function onMessage(event: MessageEvent) {
    const window = frame()?.contentWindow
    if (!window || event.source !== window || event.origin !== 'null' || over) return

    const parsed = parseGameMessage(event.data)
    if (!parsed) return
    if (parsed.kind !== 'ready') over = true
    onEvent(parsed)
  }

  globalThis.addEventListener('message', onMessage)
  return () => globalThis.removeEventListener('message', onMessage)
}
