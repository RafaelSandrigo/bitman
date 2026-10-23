import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { catalogHandler, makePublicGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { sessionHandlers } from '../../../test/session'

/* M19: a home apresenta o Bitman antes da lista de jogos. */
describe('apresentação do Bitman na home', () => {
  it('explica o que é o Bitman antes da lista, com o CTA "Jogar agora" levando ao primeiro jogo', async () => {
    server.use(...sessionHandlers(null).handlers, catalogHandler([makePublicGame(), makePublicGame({ slug: 'tetris', name: 'Tetris' })]))
    renderAt('/')

    const list = screen.getByRole('list', { name: 'Jogos' })
    await within(list).findByRole('link', { name: /Snake/ })
    const intro = screen.getByRole('region', { name: 'Bitman' })
    expect(within(intro).getByText(/revisados por pessoas/)).toBeInTheDocument()
    expect(within(intro).getByRole('link', { name: 'Jogar agora' })).toHaveAttribute('href', '/games/snake')
    expect(intro.compareDocumentPosition(list) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('sem jogos publicados, o CTA leva à lista (vazia)', async () => {
    server.use(...sessionHandlers(null).handlers, catalogHandler([]))
    renderAt('/')

    await screen.findByText('Nenhum jogo publicado ainda.')
    expect(screen.getByRole('link', { name: 'Jogar agora' })).toHaveAttribute('href', '#jogos')
  })
})
