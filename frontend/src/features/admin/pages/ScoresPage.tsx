import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { formatDateTime, formatScore } from '../../play/format'
import { RejectWithReason } from '../RejectWithReason'
import { hideScore, listScores, restoreScore, scoreFilters, type AdminScore, type ScoreFilter } from '../scores'

const filterLabels: Record<ScoreFilter, string> = { flagged: 'Sinalizados', hidden: 'Ocultos', all: 'Todos' }

function ScoreRow({ score, onChanged }: { score: AdminScore; onChanged: () => unknown }) {
  const restore = useMutation({ mutationFn: () => restoreScore(score.id), onSuccess: onChanged })

  return (
    <li aria-label={`Score de ${score.player.name} em ${score.game.name}`} className="space-y-2 rounded-lg border border-slate-800 bg-slate-900/50 p-3 text-sm">
      <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span className="text-lg font-semibold tabular-nums">{formatScore(score.value)}</span>
        <span>{score.player.name}</span>
        <Link to={`/admin/jogos/${encodeURIComponent(score.game.id)}`} className="text-emerald-400 hover:underline">
          {score.game.name}
        </Link>
        <span className="text-slate-400">v{score.version_number}</span>
        <span className="text-slate-400">{formatDateTime(score.created_at)}</span>
        {score.session_seconds !== null && <span className="text-slate-400">partida de {score.session_seconds}s</span>}
      </div>
      {score.flagged && <p className="text-amber-300">Sinalizado: {score.flag_reason}</p>}
      {score.hidden ? (
        <div className="space-y-2">
          <p className="text-slate-300">
            Oculto por {score.hidden.by ?? '—'}
            {score.hidden.at && ` em ${formatDateTime(score.hidden.at)}`}: {score.hidden.reason}
          </p>
          <Button variant="secondary" onClick={() => restore.mutate()} disabled={restore.isPending}>
            Devolver ao ranking
          </Button>
          {restore.isError && <Alert tone="error">{errorMessage(restore.error)}</Alert>}
        </div>
      ) : (
        <RejectWithReason
          label="Ocultar do ranking"
          confirmLabel="Confirmar ocultação"
          reasonLabel="Motivo"
          hint="Obrigatório. Fica registrado na auditoria; o score continua guardado e pode voltar ao ranking."
          reject={(reason) => hideScore(score.id, reason)}
          onDone={onChanged}
        />
      )}
    </li>
  )
}

/** S2: o admin revisa os scores sinalizados e decide se ficam no ranking. */
export function ScoresPage() {
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const requested = params.get('filtro')
  const filter: ScoreFilter = scoreFilters.find((value) => value === requested) ?? 'flagged'
  const [page, setPage] = useState(1)
  const scores = useQuery({
    queryKey: ['admin', 'scores', filter, page],
    queryFn: () => listScores(filter, page),
    placeholderData: keepPreviousData,
  })

  function refresh() {
    void queryClient.invalidateQueries({ queryKey: ['admin', 'scores'] })
    void queryClient.invalidateQueries({ queryKey: ['leaderboard'] })
  }

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Scores</h1>
      <p className="text-sm text-slate-400">
        Scores muito acima do padrão do jogo são sinalizados automaticamente e continuam no ranking até alguém decidir. Ocultar tira o
        score do ranking sem apagá-lo.
      </p>
      <div role="tablist" aria-label="Filtro" className="flex gap-2">
        {scoreFilters.map((value) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={value === filter}
            onClick={() => {
              setPage(1)
              setParams(value === 'flagged' ? {} : { filtro: value })
            }}
            className={`rounded-md px-3 py-1 text-sm ${value === filter ? 'bg-slate-800 font-semibold text-emerald-400' : 'text-slate-300 hover:bg-slate-900'}`}
          >
            {filterLabels[value]}
          </button>
        ))}
      </div>

      {scores.isError && <Alert tone="error">{errorMessage(scores.error)}</Alert>}
      {scores.isPending && <p className="text-slate-400">Carregando…</p>}
      {scores.data?.data.length === 0 && <p className="text-slate-400">Nenhum score aqui.</p>}

      <ul className="space-y-2">
        {scores.data?.data.map((score) => (
          <ScoreRow key={score.id} score={score} onChanged={refresh} />
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
