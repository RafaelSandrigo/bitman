import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makeGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { makeVersion } from '../../../test/versions'
import type { Game } from '../../catalog/game'
import type { Version } from '../versions'

const owner = { id: 'o1', name: 'Ada', email: 'ada@example.com' }

function serveGame(game: Game, isServed: boolean, versions: Version[] = []) {
  server.use(
    ...sessionHandlers(makeAdmin()).handlers,
    http.get(`/api/v1/admin/games/${game.id}`, () => HttpResponse.json({ data: { ...game, owner }, meta: { is_served: isServed } })),
    http.get('/api/v1/admin/versions', ({ request }) => {
      expect(new URL(request.url).searchParams.get('game_id')).toBe(game.id)
      return HttpResponse.json({ data: versions, meta: { current_page: 1, last_page: 1, total: versions.length } })
    }),
  )
}

describe('AdminGamePage', () => {
  it('mostra que o jogo está no ar e o histórico de versões', async () => {
    const game = makeGame({ status: 'published', published_version_id: 'v2', first_published_at: '2026-10-12T15:00:00+00:00' })
    serveGame(game, true, [
      makeVersion({ number: 2, status: 'published', published_at: '2026-10-12T15:00:00+00:00' }),
      makeVersion({ number: 1, status: 'validation_failed' }),
    ])
    renderAt(`/admin/jogos/${game.id}`)

    expect(await screen.findByText(/No ar\./)).toBeInTheDocument()
    const history = screen.getByRole('region', { name: 'Histórico de versões' })
    expect(await within(history).findByText(/Versão 2/)).toBeInTheDocument()
    expect(within(history).getByText('Publicada')).toBeInTheDocument()
    expect(within(history).getByText('Reprovada na validação')).toBeInTheDocument()
  })

  it('avisa quando o jogo está publicado mas a versão não está sendo servida', async () => {
    const game = makeGame({ status: 'published', published_version_id: 'v1', first_published_at: '2026-10-12T15:00:00+00:00' })
    serveGame(game, false)
    renderAt(`/admin/jogos/${game.id}`)

    expect(await screen.findByText('Publicado, mas a versão não está sendo servida. Confira os logs.')).toBeInTheDocument()
  })

  it('bloqueia com motivo (J6)', async () => {
    const game = makeGame({ status: 'published', published_version_id: 'v1', first_published_at: '2026-10-12T15:00:00+00:00' })
    serveGame(game, true)
    const bodies: unknown[] = []
    server.use(
      http.post(`/api/v1/admin/games/${game.id}/block`, async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json({ data: { ...game, status: 'blocked', block_reason: 'Minerador.' } })
      }),
    )
    const { user } = renderAt(`/admin/jogos/${game.id}`)

    await user.click(await screen.findByRole('button', { name: 'Bloquear jogo' }))
    await user.type(screen.getByLabelText('Motivo da rejeição'), 'Minerador.')
    await user.click(screen.getByRole('button', { name: 'Confirmar rejeição' }))

    await waitFor(() => expect(bodies).toEqual([{ reason: 'Minerador.' }]))
  })

  it('jogo bloqueado mostra o motivo e oferece o desbloqueio', async () => {
    const game = makeGame({ status: 'blocked', block_reason: 'Minerador.', published_version_id: 'v1', first_published_at: '2026-10-12T15:00:00+00:00' })
    serveGame(game, false)
    let unblocked = false
    server.use(
      http.post(`/api/v1/admin/games/${game.id}/unblock`, () => {
        unblocked = true
        return HttpResponse.json({ data: { ...game, status: 'published', block_reason: null } })
      }),
    )
    const { user } = renderAt(`/admin/jogos/${game.id}`)

    expect(await screen.findByText(/Motivo: Minerador\./)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Bloquear jogo' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Desbloquear' }))

    await waitFor(() => expect(unblocked).toBe(true))
  })

  it('M15: define o teto de score do jogo', async () => {
    const game = makeGame({ status: 'published', published_version_id: 'v1' })
    const bodies: unknown[] = []
    serveGame(game, true)
    server.use(
      http.patch(`/api/v1/admin/games/${game.id}/score-cap`, async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json({ data: { ...game, score_cap: 5000 } })
      }),
    )
    const { user } = renderAt(`/admin/jogos/${game.id}`)

    const section = await screen.findByRole('region', { name: 'Teto de score' })
    expect(within(section).getByText('Sem teto: só o padrão do jogo sinaliza scores.')).toBeInTheDocument()
    await user.type(within(section).getByLabelText('Teto (vazio para remover)'), '5000')
    await user.click(within(section).getByRole('button', { name: 'Salvar teto' }))

    await waitFor(() => expect(bodies).toEqual([{ score_cap: 5000 }]))
  })

  it('M15: recusa teto que não é inteiro positivo, sem chamar a API', async () => {
    const game = makeGame({ status: 'published', published_version_id: 'v1' })
    serveGame(game, true)
    const { user } = renderAt(`/admin/jogos/${game.id}`)

    const section = await screen.findByRole('region', { name: 'Teto de score' })
    await user.type(within(section).getByLabelText('Teto (vazio para remover)'), '1.5')
    await user.click(within(section).getByRole('button', { name: 'Salvar teto' }))

    expect(await within(section).findByText('Use um número inteiro maior que zero.')).toBeInTheDocument()
  })
})
