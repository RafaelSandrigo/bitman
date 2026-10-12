import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { versionStatusLabels, type VersionStatus } from '../../admin/versions'
import { gameStatusLabels, type Game } from '../../catalog/game'
import { listMyGames, listMyVersions, resubmitGame, type MyVersion } from '../api'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' })

/** Estados em que a versão parou por um problema que o dono corrige no repositório. */
const stoppedStatuses: VersionStatus[] = ['import_rejected', 'fetch_failed', 'validation_failed', 'commit_rejected', 'rejected']

function submittedName(state: unknown): string | null {
  if (typeof state === 'object' && state !== null && 'submitted' in state && typeof state.submitted === 'string') {
    return state.submitted
  }
  return null
}

/** Por que a versão parou: violações da política, motivo do admin ou falha da busca. */
function StopReason({ version }: { version: MyVersion }) {
  if (version.status === 'validation_failed') {
    return (
      <Alert tone="error">
        <p>O repositório não passou na validação:</p>
        <ul aria-label="Violações" className="mt-1 list-disc space-y-1 pl-5">
          {version.violations.map((violation, index) => (
            <li key={`${violation.code}-${index}`}>{violation.message}</li>
          ))}
        </ul>
      </Alert>
    )
  }
  if (version.status === 'import_rejected' && version.rejection_reason) {
    return <Alert tone="error">Motivo: {version.rejection_reason}</Alert>
  }
  if (version.status === 'fetch_failed' && version.fetch_failure) {
    return <Alert tone="error">{version.fetch_failure.message}</Alert>
  }
  return null
}

/** Nova versão pela URL e ref atuais do jogo; ela volta para a autorização do admin. */
function ResubmitButton({ game, stopped }: { game: Game; stopped: boolean }) {
  const queryClient = useQueryClient()
  const mutation = useMutation({
    mutationFn: () => resubmitGame(game.id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['my-versions'] }),
  })

  return (
    <div className="space-y-2">
      <p className="text-xs text-slate-400">
        O Bitman busca de novo <span className="break-all">{game.repository_url}</span> (<code>{game.default_ref}</code>).
        {stopped && ' Corrija o repositório antes de reenviar.'}
      </p>
      <Button variant="secondary" onClick={() => mutation.mutate()} disabled={mutation.isPending}>
        {stopped ? 'Enviar novamente' : 'Enviar nova versão'}
      </Button>
      {mutation.isError && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
    </div>
  )
}

function GameCard({ game, version }: { game: Game; version: MyVersion | undefined }) {
  // Enquanto o jogo não foi publicado, o que importa ao dono é onde a versão está.
  const badge = game.status === 'submitted' && version ? versionStatusLabels[version.status] : gameStatusLabels[game.status]
  const stopped = version !== undefined && stoppedStatuses.includes(version.status)
  const canResubmit = version !== undefined && !version.in_progress && game.status !== 'blocked'

  return (
    <li aria-label={game.name} className="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/50">
      <img src={game.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
      <div className="space-y-3 p-4">
        <div className="flex items-start justify-between gap-2">
          <h2 className="font-semibold">{game.name}</h2>
          <span className={`rounded px-2 py-0.5 text-xs ${stopped ? 'bg-red-950 text-red-200' : 'bg-slate-800 text-slate-200'}`}>{badge}</span>
        </div>
        <p className="text-sm text-slate-400">
          {game.repository_url} · {game.default_ref}
        </p>
        <p className="text-xs text-slate-500">
          Enviado em {dateFormat.format(new Date(game.created_at))}
          {version && ` · versão ${version.number}`}
        </p>
        {game.status === 'blocked' && game.block_reason && <Alert tone="error">Jogo bloqueado. Motivo: {game.block_reason}</Alert>}
        {game.status === 'published' && game.published_version_id && (
          <Link to={`/games/${game.slug}`} className="inline-block text-sm font-semibold text-emerald-400 hover:underline">
            Jogar →
          </Link>
        )}
        {version && <StopReason version={version} />}
        {canResubmit && <ResubmitButton game={game} stopped={stopped} />}
      </div>
    </li>
  )
}

/** O dono acompanha as submissões: onde cada versão está, por que parou e o reenvio. As decisões são do admin. */
export function MyGamesPage() {
  const location = useLocation()
  const justSubmitted = submittedName(location.state)
  const games = useQuery({ queryKey: ['my-games'], queryFn: listMyGames })
  const versions = useQuery({
    queryKey: ['my-versions'],
    queryFn: listMyVersions,
    // As decisões e a busca acontecem fora daqui: acompanha enquanto houver versão em andamento.
    refetchInterval: (query) => (query.state.data?.some((version) => version.in_progress) ? 10_000 : false),
  })
  const latestByGame = new Map(versions.data?.map((version) => [version.game_id, version]))

  return (
    <section className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">Meus jogos</h1>
        <Link to="/desenvolvedor/enviar" className="rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400">
          Enviar jogo
        </Link>
      </div>

      {justSubmitted && <Alert tone="success">{justSubmitted} foi enviado para análise.</Alert>}
      {games.isError && <Alert tone="error">{errorMessage(games.error)}</Alert>}
      {versions.isError && <Alert tone="error">{errorMessage(versions.error)}</Alert>}
      {games.isPending && <p className="text-slate-400">Carregando…</p>}
      {games.data?.length === 0 && <p className="text-slate-400">Você ainda não enviou nenhum jogo.</p>}

      <ul className="grid gap-4 sm:grid-cols-2">
        {games.data?.map((game) => (
          <GameCard key={game.id} game={game} version={latestByGame.get(game.id)} />
        ))}
      </ul>
    </section>
  )
}
