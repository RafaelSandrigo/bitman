import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { listMyScores } from '../api'
import { formatDateTime, formatScore } from '../format'

/** Histórico de partidas do jogador, da mais recente para a mais antiga. */
export function MyScoresPage() {
  const [page, setPage] = useState(1)
  const scores = useQuery({ queryKey: ['my-scores', page], queryFn: () => listMyScores(page), placeholderData: keepPreviousData })

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Minhas partidas</h1>
      {scores.isError && <Alert tone="error">{errorMessage(scores.error)}</Alert>}
      {scores.isPending && <p className="text-slate-400">Carregando…</p>}
      {scores.data?.data.length === 0 && (
        <p className="text-slate-400">
          Você ainda não registrou nenhum score.{' '}
          <Link to="/" className="text-emerald-400 hover:underline">
            Escolha um jogo
          </Link>
          .
        </p>
      )}

      <ul aria-label="Partidas" className="space-y-2">
        {scores.data?.data.map((score) => (
          <li key={score.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-slate-800 bg-slate-900/50 p-3 text-sm">
            <Link to={`/games/${score.game.slug}`} className="font-semibold text-emerald-400 hover:underline">
              {score.game.name}
            </Link>
            <span className="tabular-nums">{formatScore(score.value)}</span>
            <span className="text-slate-400">v{score.version_number}</span>
            <span className="text-slate-400">{formatDateTime(score.created_at)}</span>
            {score.hidden && <span className="rounded bg-slate-800 px-2 py-0.5 text-xs text-slate-300">Oculto do ranking pela administração</span>}
          </li>
        ))}
      </ul>

      {scores.data && scores.data.meta.last_page > 1 && (
        <div className="flex items-center gap-4 text-sm">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Anterior
          </Button>
          <span>
            Página {scores.data.meta.current_page} de {scores.data.meta.last_page}
          </span>
          <Button variant="secondary" disabled={page >= scores.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Próxima
          </Button>
        </div>
      )}
    </section>
  )
}
