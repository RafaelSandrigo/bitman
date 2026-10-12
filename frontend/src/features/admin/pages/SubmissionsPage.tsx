import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { changeGameSlug, listSubmissions, type Submission } from '../api'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

function SlugEditor({ game }: { game: Submission }) {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(game.slug)
  const mutation = useMutation({
    mutationFn: (slug: string) => changeGameSlug(game.id, slug),
    onSuccess: async () => {
      setEditing(false)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'submissions'] })
    },
  })

  if (!editing) {
    return (
      <div className="flex items-center gap-2">
        <code className="text-emerald-300">{game.slug}</code>
        <button type="button" className="text-xs text-slate-400 hover:text-emerald-400" onClick={() => setEditing(true)} aria-label={`Editar slug de ${game.name}`}>
          Editar
        </button>
      </div>
    )
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(draft.trim())
  }

  return (
    <form onSubmit={submit} className="space-y-2">
      <div className="flex gap-2">
        <input
          aria-label={`Novo slug de ${game.name}`}
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          maxLength={60}
          className="w-48 rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm"
        />
        <Button type="submit" variant="secondary" disabled={mutation.isPending}>
          Salvar
        </Button>
        <Button variant="secondary" onClick={() => setEditing(false)}>
          Cancelar
        </Button>
      </div>
      {mutation.isError && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
    </form>
  )
}

/** Fila de submissões: jogos que ainda não foram publicados, por ordem de chegada. */
export function SubmissionsPage() {
  const [page, setPage] = useState(1)
  const submissions = useQuery({
    queryKey: ['admin', 'submissions', page],
    queryFn: () => listSubmissions(page),
    placeholderData: keepPreviousData,
  })

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Submissões</h1>
      <p className="text-sm text-slate-400">
        Jogos enviados que ainda não foram publicados. O slug pode ser ajustado até a primeira publicação; depois a URL
        pública não muda mais.
      </p>

      {submissions.isError && <Alert tone="error">{errorMessage(submissions.error)}</Alert>}
      {submissions.isPending && <p className="text-slate-400">Carregando…</p>}

      {submissions.data && (
        <>
          {submissions.data.data.length === 0 && <p className="text-slate-400">Nenhuma submissão aguardando.</p>}
          <ul className="space-y-4">
            {submissions.data.data.map((game) => (
              <li key={game.id} aria-label={game.name} className="flex flex-col gap-4 rounded-lg border border-slate-800 bg-slate-900/50 p-4 sm:flex-row">
                <img src={game.thumbnail_url} alt="" className="aspect-video w-full rounded object-cover sm:w-48" />
                <div className="min-w-0 flex-1 space-y-2 text-sm">
                  <h2 className="text-base font-semibold">{game.name}</h2>
                  <SlugEditor game={game} />
                  <p className="text-slate-300">{game.description}</p>
                  <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-slate-400">
                    <dt>Autor</dt>
                    <dd>{game.author_name}</dd>
                    <dt>Dono</dt>
                    <dd>
                      {game.owner.name} ({game.owner.email})
                    </dd>
                    <dt>Repositório</dt>
                    <dd className="break-all">
                      {game.repository_url} · <code>{game.default_ref}</code>
                    </dd>
                    <dt>Enviado em</dt>
                    <dd>{dateFormat.format(new Date(game.created_at))}</dd>
                  </dl>
                  <div className="flex gap-4">
                    <Link to={`/admin/jogos/${encodeURIComponent(game.id)}`} className="text-emerald-400 hover:underline">
                      Jogo e versões
                    </Link>
                    <Link to={`/admin/auditoria?subject_type=game&subject_id=${encodeURIComponent(game.id)}`} className="text-emerald-400 hover:underline">
                      Histórico
                    </Link>
                  </div>
                </div>
              </li>
            ))}
          </ul>
          <div className="flex items-center gap-4 text-sm">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Anterior
            </Button>
            <span>
              Página {submissions.data.meta.current_page} de {submissions.data.meta.last_page} ({submissions.data.meta.total} submissões)
            </span>
            <Button variant="secondary" disabled={page >= submissions.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Próxima
            </Button>
          </div>
        </>
      )}
    </section>
  )
}
