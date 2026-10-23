import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { ApiError, errorMessage } from '../../shared/api/http'
import { useCurrentUser } from '../auth/session'
import { claimGuestScore, type ClaimedScore } from './api'
import { formatScore } from './format'
import { clearPendingScore, readPendingScore, type PendingScore } from './pendingScore'

/** Recusa definitiva (4xx: já salvo, prazo vencido, token inválido) apaga o pendente; falha de rede tenta de novo depois. */
async function claim(pending: PendingScore): Promise<ClaimedScore> {
  try {
    const claimed = await claimGuestScore(pending.id, pending.token)
    clearPendingScore()
    return claimed
  } catch (error) {
    if (error instanceof ApiError && error.status >= 400 && error.status < 500) clearPendingScore()
    throw error
  }
}

function Toast({ tone, onClose, children }: { tone: 'success' | 'error'; onClose: () => void; children: ReactNode }) {
  const border = tone === 'success' ? 'border-emerald-500/60' : 'border-red-500/60'
  return (
    <div role={tone === 'success' ? 'status' : 'alert'} className={`bitman-pop fixed right-4 bottom-4 left-4 z-50 rounded-xl border bg-slate-900 p-4 shadow-2xl sm:left-auto sm:max-w-sm ${border}`}>
      <button type="button" onClick={onClose} aria-label="Fechar aviso" className="absolute top-2 right-3 text-lg text-slate-400 hover:text-slate-100">
        ×
      </button>
      <p className="text-xs font-bold tracking-[0.3em] text-emerald-400 uppercase">Bitman</p>
      <div className="mt-1 space-y-1 pr-4 text-sm text-slate-200">{children}</div>
    </div>
  )
}

function SavedMessage({ claimed }: { claimed: ClaimedScore }) {
  return (
    <>
      <p className="font-semibold">
        Score salvo! {formatScore(claimed.value)} pontos em {claimed.game.name}
        {claimed.position !== null && <> · {claimed.position}º lugar no ranking</>}.
      </p>
      {claimed.personal_best && <p className="text-amber-300">Novo recorde pessoal!</p>}
      <Link to={`/games/${encodeURIComponent(claimed.game.slug)}`} className="font-semibold text-emerald-400 hover:underline">
        Ver o ranking
      </Link>
    </>
  )
}

/**
 * Salva na conta, assim que ela estiver confirmada, o score que a pessoa fez como visitante
 * (docs/arquitetura.md#partida-de-visitante). Fica no layout: o login e a confirmação do e-mail
 * podem terminar em qualquer página.
 */
export function PendingScoreSaver() {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const [dismissed, setDismissed] = useState(false)
  // O alvo é fixado na primeira vez: depois de salvo, o pendente some do storage, mas o aviso continua.
  const [target, setTarget] = useState<PendingScore | null>(null)
  const pending = user?.email_verified && target === null ? readPendingScore() : null
  if (pending) setTarget(pending)
  // Uma query (e não um efeito) para que a montagem dupla do StrictMode não salve duas vezes.
  const saved = useQuery({
    queryKey: ['claim-guest-score', target?.id],
    queryFn: async () => {
      const claimed = await claim(target!)
      void queryClient.invalidateQueries({ queryKey: ['leaderboard', claimed.game.slug] })
      void queryClient.invalidateQueries({ queryKey: ['my-scores'] })
      return claimed
    },
    enabled: target !== null,
    staleTime: Infinity,
    retry: false,
    refetchOnWindowFocus: false,
  })

  if (dismissed || saved.isPending) return null
  const close = () => setDismissed(true)
  if (saved.isError) {
    return (
      <Toast tone="error" onClose={close}>
        <p>Não foi possível salvar o score que você fez antes de entrar: {errorMessage(saved.error)}</p>
      </Toast>
    )
  }
  return (
    <Toast tone="success" onClose={close}>
      <SavedMessage claimed={saved.data} />
    </Toast>
  )
}
