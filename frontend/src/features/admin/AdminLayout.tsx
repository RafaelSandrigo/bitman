import { NavLink, Outlet } from 'react-router'

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-md px-3 py-2 text-sm ${isActive ? 'bg-slate-800 font-semibold text-emerald-400' : 'text-slate-300 hover:bg-slate-900'}`

export function AdminLayout() {
  return (
    <div className="space-y-6">
      <nav aria-label="Administração" className="flex gap-2 border-b border-slate-800 pb-3">
        <NavLink to="/admin/submissoes" className={linkClass}>
          Submissões
        </NavLink>
        <NavLink to="/admin/jogos" end className={linkClass}>
          Jogos
        </NavLink>
        <NavLink to="/admin/versoes" className={linkClass}>
          Versões
        </NavLink>
        <NavLink to="/admin/usuarios" className={linkClass}>
          Usuários
        </NavLink>
        <NavLink to="/admin/auditoria" className={linkClass}>
          Auditoria
        </NavLink>
      </nav>
      <Outlet />
    </div>
  )
}
