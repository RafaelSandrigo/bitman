import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { ImportDecision, NewImportButton } from '../ImportDecision'
import { listVersions, versionStatusLabels, type Version, type VersionStatus } from '../versions'

/** Filas de trabalho primeiro (portões 0, 1 e 2), depois as versões que pararam. */
const queues: VersionStatus[] = [
  'import_requested',
  'fetching',
  'awaiting_commit_review',
  'awaiting_file_review',
  'validation_failed',
  'fetch_failed',
  'import_rejected',
  'commit_rejected',
  'rejected',
]

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

function isQueue(value: string | null): value is VersionStatus {
  return queues.some((queue) => queue === value)
}

function VersionCard({ version }: { version: Version }) {
  return (
    <li aria-label={`${version.game.name} — versão ${version.number}`} className="space-y-3 rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold">
          {version.game.name} <span className="text-slate-400">· versão {version.number}</span>
        </h2>
        <Link to={`/admin/versoes/${encodeURIComponent(version.id)}`} className="text-emerald-400 hover:underline">
          Detalhes
        </Link>
      </div>
      <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-slate-400">
        <dt>Repositório</dt>
        <dd className="break-all">
          {version.repository_url} · <code>{version.requested_ref}</code>
        </dd>
        <dt>Pedido por</dt>
        <dd>
          {version.requested_by.name} em {dateFormat.format(new Date(version.created_at))}
        </dd>
        {version.commit_sha && (
          <>
            <dt>Commit</dt>
            <dd>
              <code>{version.commit_sha.slice(0, 12)}</code>
            </dd>
          </>
        )}
      </dl>

      {version.status === 'import_requested' && <ImportDecision version={version} />}
      {version.status === 'fetching' && <p className="text-slate-300">Buscando o repositório na sandbox…</p>}
      {version.status === 'awaiting_commit_review' && (
        <Link to={`/admin/versoes/${encodeURIComponent(version.id)}`} className="inline-block font-semibold text-emerald-400 hover:underline">
          Revisar commit (Portão 1) →
        </Link>
      )}
      {version.status === 'awaiting_file_review' && (
        <Link to={`/admin/versoes/${encodeURIComponent(version.id)}`} className="inline-block font-semibold text-emerald-400 hover:underline">
          Revisar arquivos (Portão 2) →
        </Link>
      )}
      {version.status === 'validation_failed' && (
        <Alert tone="error">
          Reprovada pela política de repositório. O desenvolvedor vê as violações e pode reenviar depois de corrigir.
        </Alert>
      )}
      {version.status === 'validation_failed' && <NewImportButton gameId={version.game.id} />}
      {version.status === 'fetch_failed' && (
        <>
          <Alert tone="error">{version.fetch_failure?.message ?? 'A busca falhou.'}</Alert>
          <NewImportButton gameId={version.game.id} />
        </>
      )}
      {(version.status === 'import_rejected' || version.status === 'commit_rejected' || version.status === 'rejected') && (
        <NewImportButton gameId={version.game.id} />
      )}
    </li>
  )
}

/** Filas dos portões e da busca, por ordem de chegada. */
export function VersionsPage() {
  const [params, setParams] = useSearchParams()
  const requested = params.get('estado')
  const status: VersionStatus = isQueue(requested) ? requested : 'import_requested'
  const [page, setPage] = useState(1)

  const versions = useQuery({
    queryKey: ['admin', 'versions', status, page],
    queryFn: () => listVersions(status, page),
    placeholderData: keepPreviousData,
    // A busca roda na fila: acompanha até terminar.
    refetchInterval: status === 'fetching' ? 3000 : false,
  })

  function selectQueue(queue: VersionStatus) {
    setPage(1)
    setParams(queue === 'import_requested' ? {} : { estado: queue })
  }

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Versões</h1>
      <p className="text-sm text-slate-400">
        Portão 0: nenhum repositório é acessado antes da sua autorização. A busca roda numa sandbox isolada, e o resultado
        passa pela política de repositório. Depois, o Portão 1 revisa o commit e o Portão 2, cada arquivo.
      </p>

      <div role="tablist" aria-label="Filas" className="flex flex-wrap gap-2">
        {queues.map((queue) => (
          <button
            key={queue}
            type="button"
            role="tab"
            aria-selected={queue === status}
            onClick={() => selectQueue(queue)}
            className={`rounded-md px-3 py-1 text-sm ${queue === status ? 'bg-slate-800 font-semibold text-emerald-400' : 'text-slate-300 hover:bg-slate-900'}`}
          >
            {versionStatusLabels[queue]}
          </button>
        ))}
      </div>

      {versions.isError && <Alert tone="error">{errorMessage(versions.error)}</Alert>}
      {versions.isPending && <p className="text-slate-400">Carregando…</p>}

      {versions.data && (
        <>
          {versions.data.data.length === 0 && <p className="text-slate-400">Nenhuma versão nesta fila.</p>}
          <ul className="space-y-4">
            {versions.data.data.map((version) => (
              <VersionCard key={version.id} version={version} />
            ))}
          </ul>
          {versions.data.meta.last_page > 1 && (
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
        </>
      )}
    </section>
  )
}
