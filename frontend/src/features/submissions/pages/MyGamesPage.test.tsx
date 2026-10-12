import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makeGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeDeveloper, sessionHandlers } from '../../../test/session'
import { makeMyVersion } from '../../../test/versions'
import type { MyVersion } from '../api'

function myVersions(versions: MyVersion[]) {
  return http.get('/api/v1/me/versions', () => HttpResponse.json({ data: versions }))
}

describe('MyGamesPage', () => {
  it('lista os jogos do dono com onde cada versão está, sem ações de aprovação', async () => {
    const snake = makeGame({ name: 'Snake' })
    const pacman = makeGame({ name: 'Pac-Man', status: 'published' })
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [snake, pacman] })),
      myVersions([makeMyVersion({ game_id: snake.id })]),
    )
    renderAt('/desenvolvedor/jogos')

    const snakeCard = await screen.findByRole('listitem', { name: 'Snake' })
    expect(await within(snakeCard).findByText('Aguardando autorização')).toBeInTheDocument()
    expect(within(snakeCard).getByText('https://github.com/ada/snake · main')).toBeInTheDocument()
    expect(within(snakeCard).queryByRole('button')).not.toBeInTheDocument()
    expect(within(screen.getByRole('listitem', { name: 'Pac-Man' })).getByText('Publicado')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /aprovar|publicar|autorizar/i })).not.toBeInTheDocument()
  })

  it('versão reprovada na validação: mostra as violações e reenvia, voltando para a autorização', async () => {
    const game = makeGame({ name: 'Snake' })
    let latest = makeMyVersion({
      game_id: game.id,
      status: 'validation_failed',
      in_progress: false,
      commit_sha: 'a'.repeat(40),
      violations: [{ code: 'required_file_missing', message: 'O arquivo obrigatório game.js não existe na raiz do repositório.', path: 'game.js' }],
    })
    let posted = 0
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [game] })),
      http.get('/api/v1/me/versions', () => HttpResponse.json({ data: [latest] })),
      http.post(`/api/v1/me/games/${game.id}/versions`, () => {
        posted++
        latest = makeMyVersion({ game_id: game.id, number: 2 })
        return HttpResponse.json({ data: latest }, { status: 201 })
      }),
    )
    const { user } = renderAt('/desenvolvedor/jogos')

    const card = await screen.findByRole('listitem', { name: 'Snake' })
    expect(await within(card).findByText('Reprovada na validação')).toBeInTheDocument()
    const violations = within(card).getByRole('list', { name: 'Violações' })
    expect(within(violations).getByText('O arquivo obrigatório game.js não existe na raiz do repositório.')).toBeInTheDocument()

    await user.click(within(card).getByRole('button', { name: 'Enviar novamente' }))

    expect(await within(card).findByText('Aguardando autorização')).toBeInTheDocument()
    expect(within(card).queryByRole('button', { name: 'Enviar novamente' })).not.toBeInTheDocument()
    expect(within(card).getByText(/versão 2/)).toBeInTheDocument()
    expect(posted).toBe(1)
  })

  it('mostra o motivo da rejeição do admin e a falha da busca', async () => {
    const rejected = makeGame({ name: 'Snake' })
    const failed = makeGame({ name: 'Tetris' })
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [rejected, failed] })),
      myVersions([
        makeMyVersion({ game_id: rejected.id, status: 'import_rejected', in_progress: false, rejection_reason: 'Repositório de outra pessoa.' }),
        makeMyVersion({
          game_id: failed.id,
          status: 'fetch_failed',
          in_progress: false,
          fetch_failure: { code: 'ref_not_found', message: 'A branch ou tag não existe no repositório.' },
        }),
      ]),
    )
    renderAt('/desenvolvedor/jogos')

    const rejectedCard = await screen.findByRole('listitem', { name: 'Snake' })
    expect(await within(rejectedCard).findByText('Motivo: Repositório de outra pessoa.')).toBeInTheDocument()
    expect(within(rejectedCard).getByRole('button', { name: 'Enviar novamente' })).toBeInTheDocument()
    const failedCard = screen.getByRole('listitem', { name: 'Tetris' })
    expect(within(failedCard).getByText('A branch ou tag não existe no repositório.')).toBeInTheDocument()
  })

  it('mostra o erro do servidor quando o reenvio é recusado', async () => {
    const game = makeGame({ name: 'Snake' })
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [game] })),
      myVersions([makeMyVersion({ game_id: game.id, status: 'fetch_failed', in_progress: false })]),
      http.post(`/api/v1/me/games/${game.id}/versions`, () =>
        HttpResponse.json({ message: 'Este jogo já tem uma versão em andamento.' }, { status: 422 }),
      ),
    )
    const { user } = renderAt('/desenvolvedor/jogos')

    await user.click(await screen.findByRole('button', { name: 'Enviar novamente' }))

    expect(await screen.findByText('Este jogo já tem uma versão em andamento.')).toBeInTheDocument()
  })

  it('jogo bloqueado não oferece reenvio', async () => {
    const game = makeGame({ name: 'Snake', status: 'blocked' })
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [game] })),
      myVersions([makeMyVersion({ game_id: game.id, status: 'withdrawn', in_progress: false })]),
    )
    renderAt('/desenvolvedor/jogos')

    const card = await screen.findByRole('listitem', { name: 'Snake' })
    expect(await within(card).findByText('Bloqueado')).toBeInTheDocument()
    expect(within(card).queryByRole('button')).not.toBeInTheDocument()
  })

  it('orienta quem ainda não enviou nenhum jogo', async () => {
    server.use(...sessionHandlers(makeDeveloper()).handlers, http.get('/api/v1/me/games', () => HttpResponse.json({ data: [] })), myVersions([]))
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
