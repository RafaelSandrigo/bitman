import { useSyncExternalStore } from 'react'
import { z } from 'zod'

/**
 * Score de visitante à espera de uma conta (docs/arquitetura.md#partida-de-visitante). Fica no
 * localStorage do hub para sobreviver ao login, ao cadastro e à confirmação do e-mail (que abre
 * outra aba). O jogo nunca o lê: roda em outra origem, sem storage (J1). O token só salva este
 * score pendente; não dá acesso a conta nenhuma.
 */
const KEY = 'bitman.pending-score'

const PendingScoreSchema = z.object({
  id: z.string(),
  token: z.string().regex(/^[0-9a-f]{64}$/),
  slug: z.string(),
  gameName: z.string(),
  value: z.number().int().nonnegative(),
  claimExpiresAt: z.string(),
})
export type PendingScore = z.infer<typeof PendingScoreSchema>

// Sem storage (aba privada, site data bloqueado), o score sobrevive pelo menos à navegação na SPA.
let inMemory: PendingScore | null = null

// Quem mostra o pendente (ex.: o aviso de e-mail não confirmado) é avisado quando ele muda.
let version = 0
const listeners = new Set<() => void>()
function changed() {
  version++
  for (const listener of listeners) listener()
}
function subscribe(listener: () => void) {
  listeners.add(listener)
  globalThis.addEventListener('storage', listener)
  return () => {
    listeners.delete(listener)
    globalThis.removeEventListener('storage', listener)
  }
}

/** O pendente atual, atualizado quando é guardado ou apagado (inclusive em outra aba). */
export function usePendingScore(): PendingScore | null {
  useSyncExternalStore(subscribe, () => version)
  return readPendingScore()
}

/** Storage pode estar bloqueado (aba privada) ou conter lixo: nos dois casos, não há score pendente. */
export function readPendingScore(now: Date = new Date()): PendingScore | null {
  const stored = readStored() ?? inMemory
  return stored && new Date(stored.claimExpiresAt) > now ? stored : null
}

function readStored(): PendingScore | null {
  try {
    const raw = localStorage.getItem(KEY)
    const parsed = raw === null ? null : PendingScoreSchema.safeParse(JSON.parse(raw))
    return parsed?.success ? parsed.data : null
  } catch {
    return null
  }
}

/** Só o último score fica guardado: uma partida nova de visitante substitui a anterior. */
export function savePendingScore(score: PendingScore): void {
  inMemory = score
  try {
    localStorage.setItem(KEY, JSON.stringify(score))
  } catch {
    // Fica só na memória da aba.
  }
  changed()
}

export function clearPendingScore(): void {
  inMemory = null
  try {
    localStorage.removeItem(KEY)
  } catch {
    // Nada a limpar se o storage está bloqueado.
  }
  changed()
}
