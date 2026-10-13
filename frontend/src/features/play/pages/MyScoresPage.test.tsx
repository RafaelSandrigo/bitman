import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeUser, sessionHandlers } from '../../../test/session'

const page = (data: unknown[]) => HttpResponse.json({ data, meta: { current_page: 1, last_page: 1, total: data.length } })

describe('minhas partidas', () => {
  it('lista as partidas com jogo, score, versão e se foi ocultada do ranking', async () => {
    server.use(
      ...sessionHandlers(makeUser()).handlers,
      http.get('/api/v1/me/scores', () =>
        page([
          { id: '1', game: { slug: 'snake', name: 'Snake' }, version_number: 2, value: 12000, hidden: true, created_at: '2026-10-13T12:00:00+00:00' },
          { id: '2', game: { slug: 'tetris', name: 'Tetris' }, version_number: 1, value: 50, hidden: false, created_at: '2026-10-12T12:00:00+00:00' },
        ]),
      ),
    )
    renderAt('/minhas-partidas')

    const list = await screen.findByRole('list', { name: 'Partidas' })
    const items = await within(list).findAllByRole('listitem')
    expect(within(items[0]!).getByRole('link', { name: 'Snake' })).toHaveAttribute('href', '/games/snake')
    expect(items[0]).toHaveTextContent('12.000')
    expect(items[0]).toHaveTextContent('Oculto do ranking pela administração')
    expect(items[1]).not.toHaveTextContent('Oculto')
  })

  it('sem partidas, convida a jogar', async () => {
    server.use(...sessionHandlers(makeUser()).handlers, http.get('/api/v1/me/scores', () => page([])))
    renderAt('/minhas-partidas')

    expect(await screen.findByText(/Você ainda não registrou nenhum score/)).toBeInTheDocument()
  })

  it('visitante é levado ao login', async () => {
    server.use(...sessionHandlers(null).handlers)
    const { router } = renderAt('/minhas-partidas')

    await screen.findByRole('heading', { name: 'Entrar' })
    expect(router.state.location.search).toBe('?next=%2Fminhas-partidas')
  })
})
