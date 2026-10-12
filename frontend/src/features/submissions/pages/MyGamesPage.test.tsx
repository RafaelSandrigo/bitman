import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makeGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeDeveloper, sessionHandlers } from '../../../test/session'

describe('MyGamesPage', () => {
  it('lista os jogos do dono com o status, sem ações de aprovação', async () => {
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () =>
        HttpResponse.json({ data: [makeGame({ name: 'Snake' }), makeGame({ name: 'Pac-Man', status: 'published' })] }),
      ),
    )
    renderAt('/desenvolvedor/jogos')

    const items = await screen.findAllByRole('listitem')
    expect(items).toHaveLength(2)
    expect(within(items[0]!).getByText('Em análise')).toBeInTheDocument()
    expect(within(items[1]!).getByText('Publicado')).toBeInTheDocument()
    expect(within(items[0]!).getByText('https://github.com/ada/snake · main')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /aprovar|publicar/i })).not.toBeInTheDocument()
  })

  it('orienta quem ainda não enviou nenhum jogo', async () => {
    server.use(...sessionHandlers(makeDeveloper()).handlers, http.get('/api/v1/me/games', () => HttpResponse.json({ data: [] })))
    renderAt('/desenvolvedor/jogos')

    expect(await screen.findByText('Você ainda não enviou nenhum jogo.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Enviar jogo' })).toHaveAttribute('href', '/desenvolvedor/enviar')
  })

  it('o menu mostra "Meus jogos" para o developer', async () => {
    server.use(...sessionHandlers(makeDeveloper()).handlers)
    renderAt('/')

    expect(await screen.findByRole('link', { name: 'Meus jogos' })).toHaveAttribute('href', '/desenvolvedor/jogos')
  })
})
