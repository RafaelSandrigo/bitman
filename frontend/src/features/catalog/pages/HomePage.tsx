import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { linkButton } from '../../../shared/ui/linkStyles'
import { listPublishedGames, type PublicGame } from '../game'

/** M19: o que é o Bitman e o CTA para jogar, antes da lista. */
function Intro({ firstGame }: { firstGame: PublicGame | undefined }) {
  return (
    <section aria-labelledby="bitman-intro" className="space-y-4 rounded-xl border border-slate-800 bg-gradient-to-br from-slate-900 to-emerald-950/40 p-6 sm:p-8">
      <h1 id="bitman-intro" className="text-3xl font-bold sm:text-4xl">
        Bitman
      </h1>
      <p className="max-w-2xl text-lg text-slate-300">
        Jogos de navegador feitos por desenvolvedores da comunidade, revisados por pessoas antes de ir ao ar e rodando isolados
        do resto do site. Jogue direto no navegador, sem instalar nada, e dispute o topo do ranking de cada jogo.
      </p>
      <div className="flex flex-wrap gap-3">
        {firstGame ? (
          <Link to={`/games/${firstGame.slug}`} className={linkButton.primary}>
            Jogar agora
          </Link>
        ) : (
          <a href="#jogos" className={linkButton.primary}>
            Jogar agora
          </a>
        )}
        <a href="#jogos" className={linkButton.secondary}>
          Ver todos os jogos
        </a>
      </div>
    </section>
  )
}

/** Catálogo público: só jogos publicados e não bloqueados (I9). */
export function HomePage() {
  const games = useQuery({ queryKey: ['games'], queryFn: listPublishedGames })

  return (
    <div className="space-y-8">
      <Intro firstGame={games.data?.[0]} />

      <section aria-labelledby="jogos" className="space-y-4">
        <h2 id="jogos" className="scroll-mt-4 text-xl font-semibold">
          Jogos
        </h2>
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
                  <h3 className="font-semibold">{game.name}</h3>
                  <p className="line-clamp-2 text-sm text-slate-400">{game.description}</p>
                  <p className="text-xs text-slate-500">por {game.author_name}</p>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
