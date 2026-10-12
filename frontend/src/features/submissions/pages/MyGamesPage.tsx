import { useQuery } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { gameStatusLabels } from '../../catalog/game'
import { listMyGames } from '../api'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' })

function submittedName(state: unknown): string | null {
  if (typeof state === 'object' && state !== null && 'submitted' in state && typeof state.submitted === 'string') {
    return state.submitted
  }
  return null
}

/** O dono acompanha as submissões. Somente leitura: as decisões são do admin. */
export function MyGamesPage() {
  const location = useLocation()
  const justSubmitted = submittedName(location.state)
  const games = useQuery({ queryKey: ['my-games'], queryFn: listMyGames })

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
      {games.isPending && <p className="text-slate-400">Carregando…</p>}
      {games.data?.length === 0 && <p className="text-slate-400">Você ainda não enviou nenhum jogo.</p>}

      <ul className="grid gap-4 sm:grid-cols-2">
        {games.data?.map((game) => (
          <li key={game.id} className="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/50">
            <img src={game.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
            <div className="space-y-2 p-4">
              <div className="flex items-start justify-between gap-2">
                <h2 className="font-semibold">{game.name}</h2>
                <span className="rounded bg-slate-800 px-2 py-0.5 text-xs text-slate-200">{gameStatusLabels[game.status]}</span>
              </div>
              <p className="text-sm text-slate-400">
                {game.repository_url} · {game.default_ref}
              </p>
              <p className="text-xs text-slate-500">Enviado em {dateFormat.format(new Date(game.created_at))}</p>
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}
