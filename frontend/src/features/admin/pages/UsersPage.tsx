import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import type { User } from '../../auth/api'
import { demoteDeveloper, listUsers, promoteToDeveloper } from '../api'

const roleLabels: Record<User['roles'][number], string> = { player: 'Player', developer: 'Developer', admin: 'Admin' }

export function UsersPage() {
  const queryClient = useQueryClient()
  const [draft, setDraft] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const users = useQuery({
    queryKey: ['admin', 'users', search, page],
    queryFn: () => listUsers(search, page),
    placeholderData: keepPreviousData,
  })

  const toggle = useMutation({
    mutationFn: (user: User) => (user.roles.includes('developer') ? demoteDeveloper(user.id) : promoteToDeveloper(user.id)),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin'] }),
  })

  function submitSearch(event: FormEvent) {
    event.preventDefault()
    setPage(1)
    setSearch(draft.trim())
  }

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Usuários</h1>
      <p className="text-sm text-slate-400">
        O cadastro é aberto como player. Só o admin promove a developer, e só quem confirmou o e-mail.
      </p>

      <form role="search" onSubmit={submitSearch} className="flex gap-2">
        <input
          aria-label="Buscar por nome ou e-mail"
          placeholder="Buscar por nome ou e-mail"
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          maxLength={100}
          className="w-full max-w-sm rounded-md border border-slate-700 bg-slate-900 px-3 py-2"
        />
        <Button type="submit" variant="secondary">
          Buscar
        </Button>
      </form>

      {toggle.isError && <Alert tone="error">{errorMessage(toggle.error)}</Alert>}
      {users.isError && <Alert tone="error">{errorMessage(users.error)}</Alert>}
      {users.isPending && <p className="text-slate-400">Carregando…</p>}

      {users.data && (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-800 text-slate-400">
                <tr>
                  <th className="py-2 pr-4">Nome</th>
                  <th className="py-2 pr-4">E-mail</th>
                  <th className="py-2 pr-4">Roles</th>
                  <th className="py-2">Ações</th>
                </tr>
              </thead>
              <tbody>
                {users.data.data.map((user) => {
                  const isDeveloper = user.roles.includes('developer')
                  return (
                    <tr key={user.id} className="border-b border-slate-900">
                      <td className="py-2 pr-4">{user.name}</td>
                      <td className="py-2 pr-4">
                        {user.email}
                        {!user.email_verified && <span className="ml-2 text-xs text-amber-400">(não confirmado)</span>}
                      </td>
                      <td className="py-2 pr-4">{user.roles.map((r) => roleLabels[r]).join(', ')}</td>
                      <td className="flex gap-2 py-2">
                        <Button
                          variant={isDeveloper ? 'danger' : 'secondary'}
                          disabled={toggle.isPending || (!isDeveloper && !user.email_verified)}
                          onClick={() => toggle.mutate(user)}
                          aria-label={`${isDeveloper ? 'Rebaixar' : 'Promover'} ${user.name}`}
                        >
                          {isDeveloper ? 'Rebaixar de developer' : 'Promover a developer'}
                        </Button>
                        <Link
                          to={`/admin/auditoria?user_id=${encodeURIComponent(user.id)}`}
                          className="self-center text-emerald-400 hover:underline"
                        >
                          Histórico
                        </Link>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          {users.data.data.length === 0 && <p className="text-slate-400">Nenhum usuário encontrado.</p>}
          <div className="flex items-center gap-4 text-sm">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Anterior
            </Button>
            <span>
              Página {users.data.meta.current_page} de {users.data.meta.last_page} ({users.data.meta.total} usuários)
            </span>
            <Button variant="secondary" disabled={page >= users.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Próxima
            </Button>
          </div>
        </>
      )}
    </section>
  )
}
