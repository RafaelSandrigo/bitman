import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { isGithubProfile } from '../../developer/api'
import { formatDateTime } from '../../play/format'
import {
  approveDeveloperRequest,
  listDeveloperRequests,
  rejectDeveloperRequest,
  requestStatuses,
  type AdminDeveloperRequest,
  type RequestStatus,
} from '../developerRequests'
import { RejectWithReason } from '../RejectWithReason'

const statusLabels: Record<RequestStatus, string> = { pending: 'Pendentes', approved: 'Aprovados', rejected: 'Rejeitados' }

function Decision({ request, onChanged }: { request: AdminDeveloperRequest; onChanged: () => unknown }) {
  const approve = useMutation({ mutationFn: () => approveDeveloperRequest(request.id), onSuccess: onChanged })

  if (request.status !== 'pending') {
    return (
      <p className="text-slate-400">
        {request.status === 'approved' ? 'Aprovado' : 'Rejeitado'} por {request.decided_by ?? '—'}
        {request.decided_at && ` em ${formatDateTime(request.decided_at)}`}
        {request.decision_reason && `: ${request.decision_reason}`}
      </p>
    )
  }
  return (
    <div className="flex flex-wrap items-start gap-2">
      <Button onClick={() => approve.mutate()} disabled={approve.isPending}>
        Aprovar como developer
      </Button>
      <RejectWithReason hint="Obrigatório. A pessoa vê este motivo e pode enviar outro pedido." reject={(reason) => rejectDeveloperRequest(request.id, reason)} onDone={onChanged} />
      {approve.isError && <Alert tone="error">{errorMessage(approve.error)}</Alert>}
    </div>
  )
}

/** Texto do usuário (nome, GitHub, apresentação): só como texto; o link só se for um perfil do GitHub. */
function RequestItem({ request, onChanged }: { request: AdminDeveloperRequest; onChanged: () => unknown }) {
  return (
    <li aria-label={`Pedido de ${request.user.name}`} className="space-y-2 rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm">
      <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span className="font-semibold">{request.name}</span>
        <span className="text-slate-400">
          conta: {request.user.name} ({request.user.email})
        </span>
        <span className="text-slate-400">{formatDateTime(request.created_at)}</span>
      </div>
      {isGithubProfile(request.github_url) ? (
        <a href={request.github_url} target="_blank" rel="noopener noreferrer" className="text-emerald-400 hover:underline">
          {request.github_url}
        </a>
      ) : (
        <p>{request.github_url}</p>
      )}
      <p className="whitespace-pre-line text-slate-300">{request.bio}</p>
      <Decision request={request} onChanged={onChanged} />
    </li>
  )
}

/** M18: a fila de pedidos "Seja Desenvolvedor". */
export function DeveloperRequestsPage() {
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const status: RequestStatus = requestStatuses.find((value) => value === params.get('estado')) ?? 'pending'
  const [page, setPage] = useState(1)
  const requests = useQuery({ queryKey: ['admin', 'developer-requests', status, page], queryFn: () => listDeveloperRequests(status, page), placeholderData: keepPreviousData })
  const refresh = () => void queryClient.invalidateQueries({ queryKey: ['admin', 'developer-requests'] })

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Pedidos de developer</h1>
      <div role="tablist" aria-label="Estado" className="flex gap-2">
        {requestStatuses.map((value) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={value === status}
            onClick={() => {
              setPage(1)
              setParams(value === 'pending' ? {} : { estado: value })
            }}
            className={`rounded-md px-3 py-1 text-sm ${value === status ? 'bg-slate-800 font-semibold text-emerald-400' : 'text-slate-300 hover:bg-slate-900'}`}
          >
            {statusLabels[value]}
          </button>
        ))}
      </div>
      {requests.isError && <Alert tone="error">{errorMessage(requests.error)}</Alert>}
      {requests.isPending && <p className="text-slate-400">Carregando…</p>}
      {requests.data?.data.length === 0 && <p className="text-slate-400">Nenhum pedido aqui.</p>}
      <ul className="space-y-2">
        {requests.data?.data.map((request) => (
          <RequestItem key={request.id} request={request} onChanged={refresh} />
        ))}
      </ul>
      {requests.data && requests.data.meta.last_page > 1 && (
        <div className="flex items-center gap-4 text-sm">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Anterior
          </Button>
          <span>
            Página {requests.data.meta.current_page} de {requests.data.meta.last_page}
          </span>
          <Button variant="secondary" disabled={page >= requests.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Próxima
          </Button>
        </div>
      )}
    </section>
  )
}
