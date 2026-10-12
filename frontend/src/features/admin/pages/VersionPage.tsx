import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { formatSize } from '../format'
import { ImportDecision, NewImportButton } from '../ImportDecision'
import { RejectWithReason } from '../RejectWithReason'
import { CommitDecision, FileCard } from '../Review'
import { useMarkSeen } from '../useMarkSeen'
import { useRefreshVersions } from '../useRefreshVersions'
import { getVersion, rejectVersion, versionStatusLabels, type Decision, type Report, type VersionDetail } from '../versions'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

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

/** Decisão de um portão, como linha do resumo. */
function DecisionLine({ term, decision, approved }: { term: string; decision: Decision; approved: string }) {
  if (!decision) return null
  return (
    <>
      <dt>{term}</dt>
      <dd>
        {decision.rejection_reason ? 'Rejeitado' : approved} por {decision.decided_by?.name ?? '—'} em {dateFormat.format(new Date(decision.decided_at))}
        {decision.rejection_reason && <span className="block text-slate-300">Motivo: {decision.rejection_reason}</span>}
      </dd>
    </>
  )
}

/** Portão 2: arquivos, decisão por arquivo e rejeição da versão inteira. */
function FilesSection({ version }: { version: VersionDetail }) {
  const refresh = useRefreshVersions()
  const reviewing = version.status === 'awaiting_file_review'
  const canView = reviewing || version.status === 'awaiting_commit_review'
  const approved = version.files.filter((file) => file.review_status === 'approved').length

  return (
    <section aria-label="Arquivos da versão" className="space-y-3">
      <h2 className="text-lg font-semibold">{reviewing ? 'Portão 2 — revisão dos arquivos' : 'Arquivos na quarentena'}</h2>
      {reviewing && (
        <p className="text-sm text-slate-400">
          Abra cada arquivo e decida sobre o conteúdo exibido: a decisão vale só para esse hash. {approved} de {version.files.length} aprovados.
        </p>
      )}
      {version.files.length === 0 ? (
        <p className="text-sm text-slate-400">Nenhum arquivo extraído.</p>
      ) : (
        <ul className="space-y-3">
          {version.files.map((file) => (
            <FileCard key={file.id} versionId={version.id} file={file} canView={canView} canReview={reviewing} />
          ))}
        </ul>
      )}
      {reviewing && approved === version.files.length && version.files.length > 0 && (
        <Alert tone="success">Todos os arquivos aprovados. A versão está pronta para a publicação, que chega na próxima fase.</Alert>
      )}
      {reviewing && (
        <RejectWithReason label="Rejeitar versão" reject={(reason) => rejectVersion(version.id, reason)} onDone={refresh} />
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

  useMarkSeen(version.data ? [version.data.data] : undefined)

  if (version.isError) return <Alert tone="error">{errorMessage(version.error)}</Alert>
  if (version.isPending) return <p className="text-slate-400">Carregando…</p>

  const v = version.data.data

  return (
    <article className="space-y-6">
      <header className="space-y-1">
        <Link to="/admin/versoes" className="text-sm text-emerald-400 hover:underline">
          ← Versões
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
        <DecisionLine term="Portão 0" decision={v.import_decision} approved="Autorizado" />
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
        <DecisionLine term="Portão 1" decision={v.commit_decision} approved="Commit aprovado" />
        <DecisionLine term="Rejeição" decision={v.rejection} approved="Rejeitada" />
      </dl>

      {v.status === 'import_requested' && <ImportDecision version={v} />}
      {v.status === 'fetching' && <Alert tone="info">Buscando o repositório na sandbox… esta página atualiza sozinha.</Alert>}
      {v.fetch_failure && <Alert tone="error">{v.fetch_failure.message}</Alert>}
      {(['fetch_failed', 'import_rejected', 'validation_failed', 'commit_rejected', 'rejected'] as const).some((status) => status === v.status) && (
        <NewImportButton gameId={v.game.id} />
      )}

      {v.report && <ReportSection report={v.report} />}
      {v.status === 'awaiting_commit_review' && (
        <section aria-label="Portão 1" className="space-y-3">
          <h2 className="text-lg font-semibold">Portão 1 — revisão do commit</h2>
          <p className="text-sm text-slate-400">
            Confira o repositório, a ref, o commit, os arquivos encontrados (✓) e ignorados (✗) e o tipo de mudança de cada um. Você pode abrir
            os arquivos abaixo. Aprovar libera a revisão arquivo por arquivo.
          </p>
          <CommitDecision version={v} />
        </section>
      )}
      {v.commit_sha && <FilesSection version={v} />}

      <Link to={`/admin/auditoria?subject_type=game_version&subject_id=${encodeURIComponent(v.id)}`} className="text-sm text-emerald-400 hover:underline">
        Histórico da versão
      </Link>
    </article>
  )
}
