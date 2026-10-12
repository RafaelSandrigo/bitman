import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { listPublishedGames } from '../game'

/** Catálogo público: só jogos publicados e não bloqueados (I9). */
export function HomePage() {
  const games = useQuery({ queryKey: ['games'], queryFn: listPublishedGames })

  return (
    <section className="space-y-6">
      <div className="space-y-2">
        <h1 className="text-3xl font-bold">Bitman</h1>
        <p className="text-slate-400">Jogos de navegador feitos por amigos.</p>
      </div>

      {games.isError && <Alert tone="error">{errorMessage(games.error)}</Alert>}
      {games.isPending && <p className="text-slate-400">Carregando…</p>}
      {games.data?.length === 0 && <p className="text-slate-400">Nenhum jogo publicado ainda.</p>}

      <ul aria-label="Jogos" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {games.data?.map((game) => (
          <li key={game.slug}>
            <Link
              to={`/games/${game.slug}`}
              className="block overflow-hidden rounded-lg border border-slate-800 bg-slate-900/50 transition hover:border-emerald-500"
            >
              <img src={game.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
              <div className="space-y-1 p-4">
                <h2 className="font-semibold">{game.name}</h2>
                <p className="line-clamp-2 text-sm text-slate-400">{game.description}</p>
                <p className="text-xs text-slate-500">por {game.author_name}</p>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}
