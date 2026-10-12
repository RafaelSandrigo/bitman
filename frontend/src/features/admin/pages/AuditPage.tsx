import { useInfiniteQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { listAuditEntries, type AuditEntry, type AuditFilter } from '../api'

const actionLabels: Record<string, string> = {
  'identity.role-granted': 'Role concedida',
  'identity.role-revoked': 'Role removida',
  'catalog.game-submitted': 'Jogo submetido',
  'catalog.slug-changed': 'Slug alterado',
  'versioning.import-requested': 'Importação solicitada',
  'versioning.import-authorized': 'Importação autorizada (Portão 0)',
  'versioning.import-rejected': 'Importação rejeitada (Portão 0)',
  'versioning.repository-fetched': 'Repositório buscado',
  'versioning.fetch-failed': 'Busca do repositório falhou',
  'versioning.commit-approved': 'Commit aprovado (Portão 1)',
  'versioning.commit-rejected': 'Commit rejeitado (Portão 1)',
  'versioning.file-approved': 'Arquivo aprovado (Portão 2)',
  'versioning.file-rejected': 'Arquivo rejeitado (Portão 2)',
  'versioning.version-rejected': 'Versão rejeitada',
}

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })

function describeContext(context: AuditEntry['context']): string {
  return Object.entries(context)
    .map(([key, value]) => `${key}: ${String(value)}`)
    .join(' · ')
}

export function AuditPage() {
  const [params, setParams] = useSearchParams()
  const filter: AuditFilter = { subjectType: params.get('subject_type') ?? '', subjectId: params.get('subject_id') ?? '' }
  const [draft, setDraft] = useState(filter)

  const entries = useInfiniteQuery({
    queryKey: ['admin', 'audit', filter],
    queryFn: ({ pageParam }) => listAuditEntries(filter, pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    const next = new URLSearchParams()
    if (draft.subjectType.trim()) next.set('subject_type', draft.subjectType.trim())
    if (draft.subjectId.trim()) next.set('subject_id', draft.subjectId.trim())
    setParams(next)
  }

  const rows = entries.data?.pages.flatMap((page) => page.data) ?? []

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Auditoria</h1>
      <p className="text-sm text-slate-400">Registro imutável das ações administrativas, do mais recente ao mais antigo.</p>

      <form onSubmit={submit} className="flex flex-wrap gap-2">
        <input
          aria-label="Tipo da entidade"
          placeholder="Tipo (ex.: user)"
          value={draft.subjectType}
          onChange={(e) => setDraft({ ...draft, subjectType: e.target.value })}
          className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2"
        />
        <input
          aria-label="ID da entidade"
          placeholder="ID da entidade"
          value={draft.subjectId}
          onChange={(e) => setDraft({ ...draft, subjectId: e.target.value })}
          className="w-80 rounded-md border border-slate-700 bg-slate-900 px-3 py-2"
        />
        <Button type="submit" variant="secondary">
          Filtrar
        </Button>
      </form>

      {entries.isError && <Alert tone="error">{errorMessage(entries.error)}</Alert>}
      {entries.isPending && <p className="text-slate-400">Carregando…</p>}
      {entries.isSuccess && rows.length === 0 && <p className="text-slate-400">Nenhum registro encontrado.</p>}

      {rows.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-800 text-slate-400">
              <tr>
                <th className="py-2 pr-4">Quando</th>
                <th className="py-2 pr-4">Ação</th>
                <th className="py-2 pr-4">Entidade</th>
                <th className="py-2 pr-4">Ator</th>
                <th className="py-2 pr-4">Detalhes</th>
                <th className="py-2">Motivo</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((entry) => (
                <tr key={entry.id} className="border-b border-slate-900 align-top">
                  <td className="py-2 pr-4 whitespace-nowrap">{dateFormat.format(new Date(entry.occurred_at))}</td>
                  <td className="py-2 pr-4">{actionLabels[entry.action] ?? entry.action}</td>
                  <td className="py-2 pr-4 font-mono text-xs">
                    {entry.subject_type}:{entry.subject_id}
                  </td>
                  <td className="py-2 pr-4">{entry.actor?.name ?? 'Sistema (console)'}</td>
                  <td className="py-2 pr-4">{describeContext(entry.context)}</td>
                  <td className="py-2">{entry.reason ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {entries.hasNextPage && (
        <Button variant="secondary" disabled={entries.isFetchingNextPage} onClick={() => void entries.fetchNextPage()}>
          Carregar mais
        </Button>
      )}
    </section>
  )
}
