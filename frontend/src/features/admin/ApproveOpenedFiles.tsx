import { useMutation, useQueries } from '@tanstack/react-query'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { useRefreshVersions } from './useRefreshVersions'
import { getFileContent, reviewFile, type FileContent, type VersionFile } from './versions'

/** A mesma chave do FileCard: só lê o conteúdo que o admin já abriu; nunca busca nada. */
const contentKey = (versionId: string, fileId: string) => ['admin', 'versions', 'content', versionId, fileId]

/**
 * M9: a abertura de cada arquivo continua obrigatória (ADR 0006), mas os já abertos, com o hash
 * conferido, podem ser aprovados num passo. Cada aprovação leva o hash que foi mostrado (I5).
 */
export function ApproveOpenedFiles({ versionId, files }: { versionId: string; files: VersionFile[] }) {
  const refresh = useRefreshVersions()
  const pending = files.filter((file) => file.review_status === 'pending')
  const contents = useQueries({
    queries: pending.map((file) => ({
      queryKey: contentKey(versionId, file.id),
      queryFn: () => getFileContent(versionId, file.id),
      enabled: false,
      staleTime: Infinity,
    })),
  })
  const opened = pending.filter((file, index) => (contents[index]?.data as FileContent | undefined)?.sha256 === file.sha256)
  const approve = useMutation({
    mutationFn: async () => {
      for (const file of opened) await reviewFile(versionId, file.id, { decision: 'approved', sha256: file.sha256 })
    },
    onSettled: refresh,
  })

  if (opened.length < 2) return null
  return (
    <div className="space-y-2 rounded-lg border border-emerald-900 bg-emerald-950/30 p-3 text-sm">
      <Button onClick={() => approve.mutate()} disabled={approve.isPending}>
        Aprovar os {opened.length} arquivos abertos
      </Button>
      <p className="text-xs text-slate-400">
        Só entram os que você abriu, com o hash conferido.{opened.length < pending.length && ' Os outros continuam precisando ser abertos.'}
      </p>
      {approve.isError && <Alert tone="error">{errorMessage(approve.error)}</Alert>}
    </div>
  )
}
