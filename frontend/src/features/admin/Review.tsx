import { useMutation, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { DiffViewer } from './DiffViewer'
import { FileViewer } from './FileViewer'
import { formatSize } from './format'
import { RejectWithReason } from './RejectWithReason'
import { useRefreshVersions } from './useRefreshVersions'
import { approveCommit, getFileContent, rejectCommit, reviewFile, type FileContent, type VersionDetail, type VersionFile } from './versions'

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

const changeLabels: Record<VersionFile['change_type'], string> = {
  added: 'Novo',
  modified: 'Alterado',
  unchanged: 'Sem mudança',
}

const reviewLabels: Record<VersionFile['review_status'], string> = {
  pending: 'Pendente',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
}

/** Portão 1: aprovar o commit (nunca com violação bloqueante, I3) ou rejeitar com motivo. */
export function CommitDecision({ version }: { version: Pick<VersionDetail, 'id' | 'report'> }) {
  const refresh = useRefreshVersions()
  const approve = useMutation({ mutationFn: () => approveCommit(version.id), onSuccess: refresh })
  const blocked = version.report?.blocked !== false

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-start gap-2">
        <Button onClick={() => approve.mutate()} disabled={blocked || approve.isPending}>
          Aprovar commit
        </Button>
        <RejectWithReason label="Rejeitar commit" reject={(reason) => rejectCommit(version.id, reason)} onDone={refresh} disabled={approve.isPending} />
      </div>
      {blocked && <p className="text-xs text-slate-400">Aprovação indisponível: o relatório tem violações bloqueantes.</p>}
      {approve.isError && <Alert tone="error">{errorMessage(approve.error)}</Alert>}
    </div>
  )
}

function FileHistory({ file }: { file: VersionFile }) {
  if (file.reviews.length === 0) return null
  return (
    <ol aria-label={`Decisões sobre ${file.path}`} className="space-y-1 text-xs text-slate-400">
      {file.reviews.map((review) => (
        <li key={review.id}>
          {review.decision === 'approved' ? 'Aprovado' : 'Rejeitado'} por {review.reviewer.name} em {dateFormat.format(new Date(review.decided_at))} · hash{' '}
          <code>{review.sha256.slice(0, 12)}</code>
          {review.reason && <span className="block text-slate-300">Motivo: {review.reason}</span>}
        </li>
      ))}
    </ol>
  )
}

/**
 * Portão 2: o admin abre o conteúdo, e a decisão leva o hash do que foi mostrado (I5). Sem abrir,
 * não há botão de aprovar.
 */
function FileDecision({ versionId, file, sha256 }: { versionId: string; file: VersionFile; sha256: string }) {
  const refresh = useRefreshVersions()
  const approve = useMutation({ mutationFn: () => reviewFile(versionId, file.id, { decision: 'approved', sha256 }), onSuccess: refresh })

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-start gap-2">
        <Button onClick={() => approve.mutate()} disabled={approve.isPending}>
          Aprovar {file.path}
        </Button>
        <RejectWithReason
          label={`Rejeitar ${file.path}`}
          reject={(reason) => reviewFile(versionId, file.id, { decision: 'rejected', sha256, reason })}
          onDone={refresh}
          disabled={approve.isPending}
        />
      </div>
      <p className="text-xs text-slate-400">Rejeitar um arquivo rejeita a versão inteira: todos são obrigatórios.</p>
      {approve.isError && <Alert tone="error">{errorMessage(approve.error)}</Alert>}
    </div>
  )
}

/** M7: um arquivo modificado abre no diff contra a versão no ar; o arquivo completo fica a um clique. */
function FileContentView({ file }: { file: FileContent }) {
  const [full, setFull] = useState(false)
  const previous = file.previous

  return (
    <div className="space-y-2">
      {file.previous_unavailable && <Alert tone="error">Não foi possível conferir a versão no ar; o diff não está disponível.</Alert>}
      {previous && (
        <div role="tablist" aria-label="Visualização" className="flex gap-2">
          <Button variant={full ? 'secondary' : 'primary'} role="tab" aria-selected={!full} onClick={() => setFull(false)}>
            Mudanças
          </Button>
          <Button variant={full ? 'primary' : 'secondary'} role="tab" aria-selected={full} onClick={() => setFull(true)}>
            Arquivo completo
          </Button>
        </div>
      )}
      {previous && !full ? (
        <DiffViewer path={file.path} before={previous.content} after={file.content} previousNumber={previous.version_number} />
      ) : (
        <FileViewer file={file} />
      )}
    </div>
  )
}

export function FileCard({ versionId, file, canView, canReview }: { versionId: string; file: VersionFile; canView: boolean; canReview: boolean }) {
  const [open, setOpen] = useState(false)
  const content = useQuery({
    queryKey: ['admin', 'versions', 'content', versionId, file.id],
    queryFn: () => getFileContent(versionId, file.id),
    enabled: open,
    staleTime: Infinity,
  })
  const previous = file.previous_approval
  const sameHash = content.data?.sha256 === file.sha256

  return (
    <li aria-label={file.path} className="space-y-3 rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="font-mono font-semibold">✓ {file.path}</h3>
        <span className="rounded bg-slate-800 px-2 py-0.5 text-xs">{reviewLabels[file.review_status]}</span>
      </div>
      <p className="text-slate-400">
        {formatSize(file.size)} · {changeLabels[file.change_type]} · SHA-256 <code className="break-all text-xs">{file.sha256}</code>
      </p>
      {previous && (
        <p className="text-xs text-slate-400">
          Este conteúdo foi aprovado anteriormente por {previous.reviewer.name} em {dateFormat.format(new Date(previous.decided_at))} (versão{' '}
          {previous.version_number}). Mesmo assim, precisa de nova aprovação.
        </p>
      )}
      <FileHistory file={file} />

      {canView && (
        <Button variant="secondary" onClick={() => setOpen((value) => !value)}>
          {open ? 'Fechar conteúdo' : 'Ver conteúdo'}
        </Button>
      )}
      {open && content.isPending && <p className="text-slate-400">Carregando…</p>}
      {open && content.isError && <Alert tone="error">{errorMessage(content.error)}</Alert>}
      {open && content.data && (
        <>
          <FileContentView file={content.data} />
          {!sameHash && <Alert tone="error">O conteúdo recebido não corresponde ao hash do arquivo. Não decida sobre ele.</Alert>}
          {canReview && sameHash && <FileDecision versionId={versionId} file={file} sha256={content.data.sha256} />}
        </>
      )}
    </li>
  )
}
