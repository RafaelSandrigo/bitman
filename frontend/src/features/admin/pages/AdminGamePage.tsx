import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { gameStatusLabels } from '../../catalog/game'
import { ScoreCapForm } from '../ScoreCapForm'
import { blockGame, getAdminGame, unblockGame } from '../games'
import { RejectWithReason } from '../RejectWithReason'
import { listGameVersions, versionStatusLabels } from '../versions'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

/** Jogo no admin: estado, se está no ar, bloqueio (J6) e o histórico de versões. */
export function AdminGamePage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const game = useQuery({ queryKey: ['admin', 'games', id], queryFn: () => getAdminGame(id) })
  const versions = useQuery({
    queryKey: ['admin', 'versions', 'game', id, page],
    queryFn: () => listGameVersions(id, page),
    placeholderData: keepPreviousData,
  })
  const refresh = () =>
    Promise.all([queryClient.invalidateQueries({ queryKey: ['admin', 'games', id] }), queryClient.invalidateQueries({ queryKey: ['admin', 'versions'] })])
  const unblock = useMutation({ mutationFn: () => unblockGame(id), onSuccess: refresh })

  if (game.isError) return <Alert tone="error">{errorMessage(game.error)}</Alert>
  if (game.isPending) return <p className="text-slate-400">Carregando…</p>

  const g = game.data.data
  const served = game.data.meta.is_served

  return (
    <article className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold">{g.name}</h1>
        <p className="text-sm text-slate-400">
          {gameStatusLabels[g.status]} · <code>{g.slug}</code> · enviado por {g.owner.name}
        </p>
      </header>

      <section aria-label="Situação" className="space-y-3">
        {g.status === 'published' && g.published_version_id && (
          <Alert tone={served ? 'success' : 'error'}>
            {served ? (
              <>
                No ar.{' '}
                <Link to={`/games/${g.slug}`} className="font-semibold underline">
                  Abrir a página do jogo
                </Link>
              </>
            ) : (
              'Publicado, mas a versão não está sendo servida. Confira os logs.'
            )}
          </Alert>
        )}
        {g.status === 'published' && !g.published_version_id && <Alert tone="info">Publicado antes, mas sem versão no ar agora.</Alert>}
        {g.status === 'blocked' && (
          <Alert tone="error">
            Bloqueado: fora do ar. <span className="block">Motivo: {g.block_reason}</span>
          </Alert>
        )}
        {g.status === 'published' && <RejectWithReason label="Bloquear jogo" reject={(reason) => blockGame(g.id, reason)} onDone={refresh} />}
        {g.status === 'blocked' && (
          <>
            <Button onClick={() => unblock.mutate()} disabled={unblock.isPending}>
              Desbloquear
            </Button>
            {unblock.isError && <Alert tone="error">{errorMessage(unblock.error)}</Alert>}
          </>
        )}
      </section>

      <ScoreCapForm gameId={g.id} current={g.score_cap} onSaved={refresh} />

      <Link to={`/admin/auditoria?game_id=${encodeURIComponent(g.id)}`} className="text-sm text-emerald-400 hover:underline">
        Linha do tempo de auditoria do jogo
      </Link>

      <section aria-label="Histórico de versões" className="space-y-3">
        <h2 className="text-lg font-semibold">Histórico de versões</h2>
        {versions.isError && <Alert tone="error">{errorMessage(versions.error)}</Alert>}
        <ul className="space-y-2 text-sm">
          {versions.data?.data.map((version) => (
            <li key={version.id} className="flex flex-wrap items-baseline justify-between gap-2 rounded-md border border-slate-800 p-3">
              <span>
                Versão {version.number} · <span className="font-semibold">{versionStatusLabels[version.status]}</span>
                {version.commit_sha && (
                  <>
                    {' '}
                    · <code>{version.commit_sha.slice(0, 12)}</code>
                  </>
                )}
                <span className="block text-xs text-slate-400">
                  {version.requested_ref} · pedida em {dateFormat.format(new Date(version.created_at))}
                  {version.published_at && ` · publicada em ${dateFormat.format(new Date(version.published_at))}`}
                </span>
              </span>
              <Link to={`/admin/versoes/${encodeURIComponent(version.id)}`} className="text-emerald-400 hover:underline">
                Detalhes
              </Link>
            </li>
          ))}
        </ul>
        {versions.data && versions.data.meta.last_page > 1 && (
          <div className="flex items-center gap-4 text-sm">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Anterior
            </Button>
            <span>
              Página {versions.data.meta.current_page} de {versions.data.meta.last_page}
            </span>
            <Button variant="secondary" disabled={page >= versions.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Próxima
            </Button>
          </div>
        )}
      </section>
    </article>
  )
}
