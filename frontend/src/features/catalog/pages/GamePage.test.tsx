import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { catalogHandler, makePublicGame } from '../../../test/games'
import { leaderboardHandler, makeGameSession } from '../../../test/play'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { sessionHandlers } from '../../../test/session'
import type { PublicGame } from '../game'

/** O visitante joga numa partida de visitante, cuja play_url é a da versão no ar. */
function serveGame(game: PublicGame) {
  server.use(
    ...sessionHandlers(null).handlers,
    leaderboardHandler([], game.slug),
    http.get(`/api/v1/games/${game.slug}`, () => HttpResponse.json({ data: game })),
    http.post(`/api/v1/games/${game.slug}/guest-plays`, () => HttpResponse.json({ data: makeGameSession({ play_url: game.play_url }) }, { status: 201 })),
  )
}

describe('catálogo e página do jogo', () => {
  it('a página inicial lista os jogos publicados com link para cada um', async () => {
    server.use(...sessionHandlers(null).handlers, catalogHandler([makePublicGame(), makePublicGame({ slug: 'tetris', name: 'Tetris' })]))
    renderAt('/')

    await screen.findByRole('heading', { name: 'Bitman' })
    const list = screen.getByRole('list', { name: 'Jogos' })
    expect(await within(list).findByRole('link', { name: /Snake/ })).toHaveAttribute('href', '/games/snake')
    expect(within(list).getByRole('link', { name: /Tetris/ })).toHaveAttribute('href', '/games/tetris')
  })

  it('sem jogos publicados, diz isso', async () => {
    server.use(...sessionHandlers(null).handlers, catalogHandler([]))
    renderAt('/')

    expect(await screen.findByText('Nenhum jogo publicado ainda.')).toBeInTheDocument()
  })

  it('carrega o jogo num iframe sandbox da origem isolada, sem allow-same-origin (J1, J3)', async () => {
    const game = makePublicGame()
    serveGame(game)
    renderAt('/games/snake')

    const iframe = await screen.findByTitle('Jogo: Snake')
    expect(iframe.tagName).toBe('IFRAME')
    expect(iframe).toHaveAttribute('src', game.play_url)
    expect(iframe.getAttribute('sandbox')).toBe('allow-scripts')
    expect(iframe).toHaveAttribute('referrerpolicy', 'no-referrer')
    expect(iframe).not.toHaveAttribute('allow')
    expect(screen.getByText(/Ele roda isolado/)).toBeInTheDocument()
  })

  it.each([
    ['outra origem', 'https://evil.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html'],
    ['javascript:', 'javascript:alert(document.cookie)'],
    ['o próprio hub', '/api/v1/me'],
  ])('nunca carrega uma play_url fora do formato esperado (%s)', async (_, url) => {
    serveGame(makePublicGame({ play_url: url }))
    const { container } = renderAt('/games/snake')

    expect(await screen.findByText('Não foi possível carregar o jogo com segurança.')).toBeInTheDocument()
    expect(container.querySelector('iframe')).toBeNull()
  })

  it('jogo inexistente, bloqueado ou não publicado: "não encontrado"', async () => {
    server.use(...sessionHandlers(null).handlers, http.get('/api/v1/games/fantasma', () => HttpResponse.json({ message: 'Not Found' }, { status: 404 })))
    renderAt('/games/fantasma')

    expect(await screen.findByText('Jogo não encontrado.')).toBeInTheDocument()
  })
})
