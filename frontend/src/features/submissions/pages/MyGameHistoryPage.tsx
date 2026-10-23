import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { versionStatusLabels } from '../../admin/versions'
import { listMyGameVersions, type MyVersion } from '../api'
import { StopReason } from '../StopReason'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

function VersionItem({ version }: { version: MyVersion }) {
  return (
    <li aria-label={`Versão ${version.number}`} className="space-y-2 rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="font-semibold">Versão {version.number}</h2>
        <span className="rounded bg-slate-800 px-2 py-0.5 text-xs">{versionStatusLabels[version.status]}</span>
      </div>
      <p className="text-slate-400">
        <code>{version.requested_ref}</code>
        {version.commit_sha && (
          <>
            {' '}
            · commit <code>{version.commit_sha.slice(0, 7)}</code>
          </>
        )}{' '}
        · pedida em {dateFormat.format(new Date(version.created_at))}
      </p>
      <StopReason version={version} />
    </li>
  )
}

/** M2: o dono vê todas as versões do jogo, com o estado e o motivo de cada uma. */
export function MyGameHistoryPage() {
  const { id = '' } = useParams()
  const [page, setPage] = useState(1)
  const history = useQuery({ queryKey: ['my-game-versions', id, page], queryFn: () => listMyGameVersions(id, page), placeholderData: keepPreviousData })

  if (history.isError) return <Alert tone="error">{errorMessage(history.error)}</Alert>
  if (history.isPending) return <p className="text-slate-400">Carregando…</p>

  const { data: versions, meta } = history.data
  return (
    <section className="space-y-4">
      <Link to="/desenvolvedor/jogos" className="text-sm text-emerald-400 hover:underline">
        ← Meus jogos
      </Link>
      <h1 className="text-2xl font-bold">{meta.game.name}: histórico de versões</h1>
      {versions.length === 0 && <p className="text-slate-400">Nenhuma versão ainda.</p>}
      <ul className="space-y-2">
        {versions.map((version) => (
          <VersionItem key={version.id} version={version} />
        ))}
      </ul>
      {meta.last_page > 1 && (
        <div className="flex items-center gap-4 text-sm">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Anterior
          </Button>
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <Button variant="secondary" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Próxima
          </Button>
        </div>
      )}
    </section>
  )
}
