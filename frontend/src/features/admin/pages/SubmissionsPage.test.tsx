import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makeGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'

const owner = { id: '01jabcdefghjkmnpqrstv99999', name: 'Ada', email: 'ada@example.com' }

function page(games: ReturnType<typeof makeGame>[]) {
  return { data: games.map((game) => ({ ...game, owner })), meta: { current_page: 1, last_page: 1, total: games.length } }
}

describe('SubmissionsPage', () => {
  it('mostra cada submissão com o dono', async () => {
    const game = makeGame()
    server.use(...sessionHandlers(makeAdmin()).handlers, http.get('/api/v1/admin/submissions', () => HttpResponse.json(page([game]))))
    renderAt('/admin/submissoes')

    const item = await screen.findByRole('listitem', { name: 'Snake' })
    expect(within(item).getByText('Ada (ada@example.com)')).toBeInTheDocument()
    expect(within(item).getByText('snake')).toBeInTheDocument()
    expect(within(item).getByRole('link', { name: 'Histórico' })).toHaveAttribute(
      'href',
      `/admin/auditoria?game_id=${game.id}`,
    )
  })

  it('ajusta o slug e recarrega a fila', async () => {
    let game = makeGame()
    let body: unknown
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/submissions', () => HttpResponse.json(page([game]))),
      http.patch(`/api/v1/admin/games/${game.id}/slug`, async ({ request }) => {
        body = await request.json()
        game = { ...game, slug: 'snake-classico' }
        return HttpResponse.json({ data: game })
      }),
    )
    const { user } = renderAt('/admin/submissoes')

    await user.click(await screen.findByRole('button', { name: 'Editar slug de Snake' }))
    const input = screen.getByRole('textbox', { name: 'Novo slug de Snake' })
    await user.clear(input)
    await user.type(input, 'snake-classico')
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    expect(await screen.findByText('snake-classico')).toBeInTheDocument()
    expect(body).toEqual({ slug: 'snake-classico' })
  })

  it('mostra a regra recusada pelo servidor ao editar o slug', async () => {
    const game = makeGame()
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/submissions', () => HttpResponse.json(page([game]))),
      http.patch(`/api/v1/admin/games/${game.id}/slug`, () =>
        HttpResponse.json({ message: 'O slug pacman já é usado por outro jogo.' }, { status: 422 }),
      ),
    )
    const { user } = renderAt('/admin/submissoes')

    await user.click(await screen.findByRole('button', { name: 'Editar slug de Snake' }))
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('O slug pacman já é usado por outro jogo.')
  })

  it('rejeita resposta com thumbnail fora do caminho esperado (zero trust)', async () => {
    const game = makeGame({ thumbnail_url: 'https://evil.example/x.png' })
    server.use(...sessionHandlers(makeAdmin()).handlers, http.get('/api/v1/admin/submissions', () => HttpResponse.json(page([game]))))
    renderAt('/admin/submissoes')

    // A consulta é repetida uma vez antes de falhar (retry: 1).
    expect(await screen.findByRole('alert', {}, { timeout: 3000 })).toHaveTextContent('Algo deu errado')
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })
})
