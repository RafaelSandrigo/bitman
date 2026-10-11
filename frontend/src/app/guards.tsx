import { Navigate, Outlet, useLocation, useSearchParams } from 'react-router'
import { safeNextPath, useCurrentUser } from '../features/auth/session'
import { Alert } from '../shared/ui/Alert'

function Loading() {
  return <p className="text-slate-400">Carregando…</p>
}

/**
 * Login, cadastro e senha. Quando a sessão passa a existir (inclusive logo após entrar ou se
 * cadastrar), é este guard que leva o usuário ao destino: o `?next=` interno ou o `home` da rota.
 * As páginas só atualizam a sessão; assim não há duas navegações concorrentes.
 */
export function GuestOnly({ home = '/' }: { home?: string }) {
  const { data: user, isPending } = useCurrentUser()
  const [params] = useSearchParams()

  if (isPending) return <Loading />
  if (!user) return <Outlet />
  const next = params.get('next')
  return <Navigate to={next ? safeNextPath(next) : home} replace />
}

/**
 * Área admin: espelha o grupo de rotas do backend (sessão + e-mail verificado + role admin).
 * É só conveniência de interface; quem autoriza de fato é a API (I12).
 */
export function AdminGate() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) return <Loading />
  if (!user) return <Navigate to={`/entrar?next=${encodeURIComponent(location.pathname + location.search)}`} replace />
  if (!user.roles.includes('admin')) {
    return (
      <section className="space-y-4">
        <h1 className="text-2xl font-semibold">Acesso negado</h1>
        <p className="text-slate-400">Esta área é restrita aos administradores.</p>
      </section>
    )
  }
  if (!user.email_verified) return <Alert tone="error">Confirme seu e-mail para acessar a administração.</Alert>

  return <Outlet />
}
