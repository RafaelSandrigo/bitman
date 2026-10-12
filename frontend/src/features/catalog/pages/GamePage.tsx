import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { ApiError, errorMessage } from '../../../shared/api/http'
import { isPlayUrl } from '../../../shared/config'
import { Alert } from '../../../shared/ui/Alert'
import { getPublishedGame } from '../game'

/**
 * Página do jogo. O jogo roda num iframe da origem isolada (ADR 0004):
 * - `sandbox="allow-scripts"` sem `allow-same-origin` → origem opaca, sem cookies nem storage (J1);
 * - sem `allow-top-navigation`, `allow-popups`, `allow-forms` e `allow-modals` (J3);
 * - nenhum recurso liberado via `allow` e sem Referer.
 * A borda e o aviso deixam claro onde começa o conteúdo de terceiros (J3).
 */
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

      <div className="rounded-lg border-2 border-dashed border-amber-700/70 p-2">
        {isPlayUrl(data.play_url) ? (
          <iframe
            title={`Jogo: ${data.name}`}
            src={data.play_url}
            sandbox="allow-scripts"
            referrerPolicy="no-referrer"
            className="aspect-video w-full rounded bg-black"
          />
        ) : (
          <Alert tone="error">Não foi possível carregar o jogo com segurança.</Alert>
        )}
        <p className="mt-2 text-xs text-amber-200/80">
          Conteúdo enviado por {data.author_name}. Ele roda isolado: não acessa a sua conta, a internet nem outras páginas.
        </p>
      </div>

      <p className="whitespace-pre-line text-slate-300">{data.description}</p>
    </article>
  )
}
