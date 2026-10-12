import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { ImportDecision, NewImportButton } from '../ImportDecision'
import { getVersion, versionStatusLabels, type Report, type VersionFile } from '../versions'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

const changeLabels: Record<VersionFile['change_type'], string> = {
  added: 'Novo',
  modified: 'Alterado',
  unchanged: 'Sem mudança',
}

function formatSize(bytes: number | null): string {
  if (bytes === null) return 'acima do limite'
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KiB`
}

/** Tudo que vem do repositório é exibido como texto (o React escapa): nunca HTML. */
function ReportSection({ report }: { report: Report }) {
  return (
    <section aria-label="Política de repositório" className="space-y-3">
      <h2 className="text-lg font-semibold">Política de repositório</h2>
      {report.blocked ? (
        <Alert tone="error">
          <p className="font-semibold">Versão bloqueada: o commit não poderá ser aprovado.</p>
          <ul className="mt-2 list-disc space-y-1 pl-5">
            {report.violations.map((violation, index) => (
              <li key={`${violation.code}-${index}`}>
                {violation.message} <code className="text-xs opacity-70">({violation.code})</code>
              </li>
            ))}
          </ul>
        </Alert>
      ) : (
        <Alert tone="success">Nenhuma violação.</Alert>
      )}

      {report.ignored_total > 0 && (
        <details className="rounded-md border border-slate-800 p-3 text-sm">
          <summary className="cursor-pointer text-slate-300">
            {report.ignored_total} {report.ignored_total === 1 ? 'arquivo ignorado' : 'arquivos ignorados'} (nunca entram na versão)
          </summary>
          <ul className="mt-2 space-y-1 font-mono text-xs text-slate-400">
            {report.ignored.map((file) => (
              <li key={file.path} className="break-all">
                ✗ {file.path} · {file.mode} · {formatSize(file.size)}
              </li>
            ))}
          </ul>
          {report.ignored.length < report.ignored_total && (
            <p className="mt-2 text-xs text-slate-500">Mostrando {report.ignored.length} de {report.ignored_total}.</p>
          )}
        </details>
      )}
    </section>
  )
}

function FilesSection({ files }: { files: VersionFile[] }) {
  return (
    <section aria-label="Arquivos da versão" className="space-y-3">
      <h2 className="text-lg font-semibold">Arquivos na quarentena</h2>
      {files.length === 0 ? (
        <p className="text-sm text-slate-400">Nenhum arquivo extraído.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="text-slate-400">
              <tr>
                <th className="py-2 pr-4">Arquivo</th>
                <th className="py-2 pr-4">Tamanho</th>
                <th className="py-2 pr-4">Mudança</th>
                <th className="py-2">SHA-256</th>
              </tr>
            </thead>
            <tbody>
              {files.map((file) => (
                <tr key={file.id} className="border-t border-slate-800">
                  <td className="py-2 pr-4">✓ {file.path}</td>
                  <td className="py-2 pr-4">{formatSize(file.size)}</td>
                  <td className="py-2 pr-4">{changeLabels[file.change_type]}</td>
                  <td className="py-2">
                    <code className="break-all text-xs">{file.sha256}</code>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

export function VersionPage() {
  const { id = '' } = useParams()
  const version = useQuery({
    queryKey: ['admin', 'versions', 'detail', id],
    queryFn: () => getVersion(id),
    refetchInterval: (query) => (query.state.data?.data.status === 'fetching' ? 3000 : false),
  })

  if (version.isError) return <Alert tone="error">{errorMessage(version.error)}</Alert>
  if (version.isPending) return <p className="text-slate-400">Carregando…</p>

  const v = version.data.data
  const decision = v.import_decision

  return (
    <article className="space-y-6">
      <header className="space-y-1">
        <Link to="/admin/importacoes" className="text-sm text-emerald-400 hover:underline">
          ← Importações
        </Link>
        <h1 className="text-2xl font-bold">
          {v.game.name} <span className="text-slate-400">· versão {v.number}</span>
        </h1>
        <p className="text-sm font-semibold text-slate-300">{versionStatusLabels[v.status]}</p>
      </header>

      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm text-slate-400">
        <dt>Repositório</dt>
        <dd className="break-all">{v.repository_url}</dd>
        <dt>Ref pedida</dt>
        <dd>
          <code>{v.requested_ref}</code>
        </dd>
        <dt>Pedido por</dt>
        <dd>
          {v.requested_by.name} em {dateFormat.format(new Date(v.created_at))}
        </dd>
        {decision && (
          <>
            <dt>Portão 0</dt>
            <dd>
              {decision.rejection_reason ? 'Rejeitado' : 'Autorizado'} por {decision.decided_by?.name ?? '—'} em{' '}
              {dateFormat.format(new Date(decision.decided_at))}
              {decision.rejection_reason && <span className="block text-slate-300">Motivo: {decision.rejection_reason}</span>}
            </dd>
          </>
        )}
        {v.commit_sha && (
          <>
            <dt>Commit</dt>
            <dd>
              <code className="break-all">{v.commit_sha}</code>
            </dd>
          </>
        )}
        {v.fetched_at && (
          <>
            <dt>Buscado em</dt>
            <dd>{dateFormat.format(new Date(v.fetched_at))}</dd>
          </>
        )}
      </dl>

      {v.status === 'import_requested' && <ImportDecision version={v} />}
      {v.status === 'fetching' && <Alert tone="info">Buscando o repositório na sandbox… esta página atualiza sozinha.</Alert>}
      {v.fetch_failure && <Alert tone="error">{v.fetch_failure.message}</Alert>}
      {(v.status === 'fetch_failed' || v.status === 'import_rejected' || v.status === 'validation_failed') && (
        <NewImportButton gameId={v.game.id} />
      )}

      {v.report && <ReportSection report={v.report} />}
      {v.commit_sha && <FilesSection files={v.files} />}

      <Link to={`/admin/auditoria?subject_type=game_version&subject_id=${encodeURIComponent(v.id)}`} className="text-sm text-emerald-400 hover:underline">
        Histórico da versão
      </Link>
    </article>
  )
}
