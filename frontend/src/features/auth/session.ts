import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchCurrentUser, logout, type CurrentUser, type Permission } from './api'

export const currentUserKey = ['current-user'] as const

export function useCurrentUser() {
  return useQuery({ queryKey: currentUserKey, queryFn: fetchCurrentUser, staleTime: 60_000 })
}

export function hasPermission(user: CurrentUser | null | undefined, permission: Permission): boolean {
  return user?.permissions.includes(permission) ?? false
}

/** Recarrega o usuário da sessão (após login, cadastro, verificação). */
export function useRefreshSession() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: currentUserKey })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      // Nada da sessão anterior pode sobreviver no cache.
      queryClient.clear()
      queryClient.setQueryData(currentUserKey, null)
    },
  })
}

/** Só caminhos internos: evita redirecionamento aberto via ?next=. */
export function safeNextPath(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') && !next.startsWith('/\\') ? next : '/'
}
