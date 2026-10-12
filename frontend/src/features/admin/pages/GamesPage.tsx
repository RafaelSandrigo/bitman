import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { listSubmissions } from '../api'

const tabs = [
  { status: 'published', label: 'Publicados' },
  { status: 'blocked', label: 'Bloqueados' },
] as const
type Tab = (typeof tabs)[number]['status']

/** Jogos que já foram ao ar: daqui o admin chega ao bloqueio (J6) e ao histórico de versões. */
export function GamesPage() {
  const [params, setParams] = useSearchParams()
  const status: Tab = params.get('estado') === 'blocked' ? 'blocked' : 'published'
  const [page, setPage] = useState(1)
  const games = useQuery({
    queryKey: ['admin', 'games', 'list', status, page],
    queryFn: () => listSubmissions(page, status),
    placeholderData: keepPreviousData,
  })

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Jogos</h1>
      <div role="tablist" aria-label="Estado" className="flex gap-2">
        {tabs.map((tab) => (
          <button
            key={tab.status}
            type="button"
            role="tab"
            aria-selected={tab.status === status}
            onClick={() => {
              setPage(1)
              setParams(tab.status === 'published' ? {} : { estado: tab.status })
            }}
            className={`rounded-md px-3 py-1 text-sm ${tab.status === status ? 'bg-slate-800 font-semibold text-emerald-400' : 'text-slate-300 hover:bg-slate-900'}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {games.isError && <Alert tone="error">{errorMessage(games.error)}</Alert>}
      {games.isPending && <p className="text-slate-400">Carregando…</p>}
      {games.data?.data.length === 0 && <p className="text-slate-400">Nenhum jogo aqui.</p>}

      <ul className="space-y-2">
        {games.data?.data.map((game) => (
          <li key={game.id} className="flex items-center gap-4 rounded-lg border border-slate-800 bg-slate-900/50 p-3 text-sm">
            <img src={game.thumbnail_url} alt="" className="aspect-video w-24 rounded object-cover" />
            <div className="min-w-0 flex-1">
              <p className="font-semibold">{game.name}</p>
              <p className="text-xs text-slate-400">
                <code>{game.slug}</code> · {game.owner.name}
              </p>
            </div>
            <Link to={`/admin/jogos/${encodeURIComponent(game.id)}`} className="text-emerald-400 hover:underline">
              Gerenciar
            </Link>
          </li>
        ))}
      </ul>

      {games.data && games.data.meta.last_page > 1 && (
        <div className="flex items-center gap-4 text-sm">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Anterior
          </Button>
          <span>
            Página {games.data.meta.current_page} de {games.data.meta.last_page}
          </span>
          <Button variant="secondary" disabled={page >= games.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Próxima
          </Button>
        </div>
      )}
    </section>
  )
}
