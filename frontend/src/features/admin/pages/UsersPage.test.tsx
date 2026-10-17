import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, makeUser, sessionHandlers } from '../../../test/session'

function page(users: ReturnType<typeof makeUser>[]) {
  return { data: users, meta: { current_page: 1, last_page: 1, total: users.length } }
}

describe('UsersPage', () => {
  it('promove um player a developer e recarrega a lista', async () => {
    const ada = makeUser({ name: 'Ada' })
    let roles: ('player' | 'developer')[] = ['player']
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/users', () => HttpResponse.json(page([{ ...ada, roles }]))),
      http.post(`/api/v1/admin/users/${ada.id}/developer`, () => {
        roles = ['player', 'developer']
        return HttpResponse.json({ data: { ...ada, roles } })
      }),
    )
    const { user } = renderAt('/admin/usuarios')

    await user.click(await screen.findByRole('button', { name: 'Promover Ada' }))

    expect(await screen.findByRole('button', { name: 'Rebaixar Ada' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Player, Developer' })).toBeInTheDocument()
  })

  it('não oferece promoção a quem não confirmou o e-mail', async () => {
    const ada = makeUser({ name: 'Ada', email_verified: false })
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/users', () => HttpResponse.json(page([ada]))),
    )
    renderAt('/admin/usuarios')

    expect(await screen.findByRole('button', { name: 'Promover Ada' })).toBeDisabled()
    expect(screen.getByText('(não confirmado)')).toBeInTheDocument()
  })

  it('mostra a regra de negócio recusada pelo servidor', async () => {
    const ada = makeUser({ name: 'Ada' })
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/users', () => HttpResponse.json(page([ada]))),
      http.post(`/api/v1/admin/users/${ada.id}/developer`, () =>
        HttpResponse.json({ message: 'O usuário já tem a role developer.' }, { status: 422 }),
      ),
    )
    const { user } = renderAt('/admin/usuarios')

    await user.click(await screen.findByRole('button', { name: 'Promover Ada' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('O usuário já tem a role developer.')
  })

  it('busca por nome ou e-mail e liga ao histórico de auditoria', async () => {
    const ada = makeUser({ name: 'Ada' })
    const searches: (string | null)[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/users', ({ request }) => {
        searches.push(new URL(request.url).searchParams.get('search'))
        return HttpResponse.json(page([ada]))
      }),
    )
    const { user } = renderAt('/admin/usuarios')

    await user.type(await screen.findByRole('textbox', { name: /buscar/i }), 'ada')
    await user.click(screen.getByRole('button', { name: 'Buscar' }))

    await screen.findByRole('cell', { name: 'Ada' })
    expect(searches).toContain('ada')
    const row = screen.getByRole('row', { name: /ada/i })
    expect(within(row).getByRole('link', { name: 'Histórico' })).toHaveAttribute(
      'href',
      `/admin/auditoria?user_id=${ada.id}`,
    )
  })
})
