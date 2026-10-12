import { useMutation } from '@tanstack/react-query'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { RejectWithReason } from './RejectWithReason'
import { useRefreshVersions } from './useRefreshVersions'
import { authorizeImport, rejectImport, requestNewImport, type Version } from './versions'

/** Portão 0: autorizar o acesso ao repositório ou rejeitar com motivo. */
export function ImportDecision({ version }: { version: Pick<Version, 'id' | 'repository_url' | 'requested_ref'> }) {
  const refresh = useRefreshVersions()
  const authorize = useMutation({ mutationFn: () => authorizeImport(version.id), onSuccess: refresh })

  return (
    <div className="space-y-3">
      <p className="text-xs text-slate-400">
        Autorizar permite que o Bitman acesse <span className="break-all">{version.repository_url}</span> (
        <code>{version.requested_ref}</code>) pela sandbox. Nada é buscado antes disso.
      </p>
      <div className="flex flex-wrap items-start gap-2">
        <Button onClick={() => authorize.mutate()} disabled={authorize.isPending}>
          Autorizar importação
        </Button>
        <RejectWithReason reject={(reason) => rejectImport(version.id, reason)} onDone={refresh} disabled={authorize.isPending} />
      </div>
      {authorize.isError && <Alert tone="error">{errorMessage(authorize.error)}</Alert>}
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
