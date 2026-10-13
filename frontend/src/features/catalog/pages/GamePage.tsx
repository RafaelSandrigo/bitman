import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { ApiError, errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { GamePlayer } from '../../play/GamePlayer'
import { Leaderboard } from '../../play/Leaderboard'
import { getPublishedGame } from '../game'

/** Página do jogo: a partida (iframe isolado + sessão de score) e o ranking. */
export function GamePage() {
  const { slug = '' } = useParams()
  const game = useQuery({ queryKey: ['games', slug], queryFn: () => getPublishedGame(slug), retry: false })

  if (game.isPending) return <p className="text-slate-400">Carregando…</p>
  if (game.isError) {
    const missing = game.error instanceof ApiError && game.error.status === 404
    return (
      <section className="space-y-4">
        <Alert tone="error">{missing ? 'Jogo não encontrado.' : errorMessage(game.error)}</Alert>
        <Link to="/" className="text-sm text-emerald-400 hover:underline">
          ← Todos os jogos
        </Link>
      </section>
    )
  }

  const data = game.data

  return (
    <article className="space-y-4">
      <header className="space-y-1">
        <Link to="/" className="text-sm text-emerald-400 hover:underline">
          ← Todos os jogos
        </Link>
        <h1 className="text-2xl font-bold">{data.name}</h1>
        <p className="text-sm text-slate-400">
          por {data.author_name} · versão {data.version_number}
        </p>
      </header>

      <GamePlayer game={data} />

      <p className="whitespace-pre-line text-slate-300">{data.description}</p>

      <Leaderboard slug={data.slug} />
    </article>
  )
}
