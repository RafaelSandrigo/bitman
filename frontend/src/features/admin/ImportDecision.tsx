import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { errorMessage, fieldErrors } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { TextArea } from '../../shared/ui/TextArea'
import { authorizeImport, rejectImport, requestNewImport, type Version } from './versions'

/** Recarrega as filas e o detalhe depois de qualquer decisão. */
function useRefreshVersions() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: ['admin', 'versions'] })
}

/** Portão 0: autorizar o acesso ao repositório ou rejeitar com motivo. */
export function ImportDecision({ version }: { version: Pick<Version, 'id' | 'repository_url' | 'requested_ref'> }) {
  const refresh = useRefreshVersions()
  const [rejecting, setRejecting] = useState(false)
  const [reason, setReason] = useState('')

  const authorize = useMutation({ mutationFn: () => authorizeImport(version.id), onSuccess: refresh })
  const reject = useMutation({ mutationFn: () => rejectImport(version.id, reason), onSuccess: refresh })
  const pending = authorize.isPending || reject.isPending

  function submitRejection(event: FormEvent) {
    event.preventDefault()
    reject.mutate()
  }

  return (
    <div className="space-y-3">
      <p className="text-xs text-slate-400">
        Autorizar permite que o Bitman acesse <span className="break-all">{version.repository_url}</span> (
        <code>{version.requested_ref}</code>) pela sandbox. Nada é buscado antes disso.
      </p>
      {!rejecting && (
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => authorize.mutate()} disabled={pending}>
            Autorizar importação
          </Button>
          <Button variant="danger" onClick={() => setRejecting(true)} disabled={pending}>
            Rejeitar
          </Button>
        </div>
      )}
      {rejecting && (
        <form onSubmit={submitRejection} className="space-y-2">
          <TextArea
            label="Motivo da rejeição"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            maxLength={1000}
            rows={3}
            error={fieldErrors(reject.error).reason}
            hint="Obrigatório. O desenvolvedor vê este motivo, e ele fica registrado na auditoria."
          />
          <div className="flex gap-2">
            <Button type="submit" variant="danger" disabled={pending}>
              Confirmar rejeição
            </Button>
            <Button variant="secondary" onClick={() => setRejecting(false)} disabled={pending}>
              Cancelar
            </Button>
          </div>
        </form>
      )}
      {authorize.isError && <Alert tone="error">{errorMessage(authorize.error)}</Alert>}
      {reject.isError && !fieldErrors(reject.error).reason && <Alert tone="error">{errorMessage(reject.error)}</Alert>}
    </div>
  )
}

/** Depois de uma busca que falhou ou de uma rejeição: nova versão, de volta ao Portão 0. */
export function NewImportButton({ gameId }: { gameId: string }) {
  const refresh = useRefreshVersions()
  const mutation = useMutation({ mutationFn: () => requestNewImport(gameId), onSuccess: refresh })

  return (
    <div className="space-y-2">
      <Button variant="secondary" onClick={() => mutation.mutate()} disabled={mutation.isPending || mutation.isSuccess}>
        Pedir nova importação
      </Button>
      {mutation.isSuccess && <Alert tone="success">Nova versão criada. Ela aguarda autorização.</Alert>}
      {mutation.isError && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
    </div>
  )
}
