import { useQueryClient } from '@tanstack/react-query'

/** Recarrega as filas e o detalhe depois de qualquer decisão. */
export function useRefreshVersions() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: ['admin', 'versions'] })
}
