import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { markVersionsSeen, type Version } from './versions'

/**
 * Exibir uma versão nova para o admin a marca como vista. A tag "Novo" continua na tela atual
 * (a lista não é recarregada); só os contadores das filas são atualizados.
 */
export function useMarkSeen(versions: Pick<Version, 'id' | 'status' | 'is_new'>[] | undefined) {
  const queryClient = useQueryClient()
  const sent = useRef(new Set<string>())
  const { mutate } = useMutation({
    mutationFn: markVersionsSeen,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'versions', 'summary'] }),
  })

  useEffect(() => {
    const unseen = (versions ?? []).filter((version) => version.is_new && !sent.current.has(`${version.id}:${version.status}`))
    if (unseen.length === 0) return
    for (const version of unseen) sent.current.add(`${version.id}:${version.status}`)
    mutate(unseen)
  }, [versions, mutate])
}
