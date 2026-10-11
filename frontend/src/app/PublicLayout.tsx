import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useCurrentUser, useLogout } from '../features/auth/session'

function UserMenu() {
  const { data: user } = useCurrentUser()
  const logout = useLogout()
  const navigate = useNavigate()

  if (!user) {
    return (
      <div className="flex gap-4 text-sm">
        <Link to="/entrar" className="hover:text-emerald-400">
          Entrar
        </Link>
        <Link to="/cadastro" className="font-semibold text-emerald-400 hover:underline">
          Criar conta
        </Link>
      </div>
    )
  }

  return (
    <div className="flex items-center gap-4 text-sm">
      {user.roles.includes('admin') && (
        <NavLink to="/admin" className="hover:text-emerald-400">
          Admin
        </NavLink>
      )}
      <span className="text-slate-400">{user.name}</span>
      <button
        type="button"
        className="hover:text-emerald-400"
        disabled={logout.isPending}
        onClick={() => logout.mutate(undefined, { onSuccess: () => void navigate('/') })}
      >
        Sair
      </button>
    </div>
  )
}

function UnverifiedBanner() {
  const { data: user } = useCurrentUser()
  if (!user || user.email_verified) return null

  return (
    <div className="border-b border-amber-900 bg-amber-950/60 px-4 py-2 text-center text-sm text-amber-200">
      Confirme seu e-mail para jogar e aparecer nos rankings.{' '}
      <Link to="/verificar-email" className="font-semibold underline">
        Saiba mais
      </Link>
    </div>
  )
}

export function PublicLayout() {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800">
        <nav className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <Link to="/" className="text-lg font-bold tracking-tight">
            Bitman
          </Link>
          <UserMenu />
        </nav>
      </header>
      <UnverifiedBanner />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
