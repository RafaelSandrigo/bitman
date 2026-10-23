import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makePublicGame } from '../../test/games'
import { leaderboardHandler, makeGameSession, postFromGame } from '../../test/play'
import { renderAt } from '../../test/render'
import { server } from '../../test/server'
import { makeUser, sessionHandlers } from '../../test/session'
import type { CurrentUser } from '../auth/api'
import { readPendingScore, savePendingScore, type PendingScore } from './pendingScore'

/*
| Partida de visitante (docs/arquitetura.md#partida-de-visitante): o Bitman avisa que não está
| logado, guarda o score e o salva depois do login (A5).
*/

const game = makePublicGame()
const gameOver = (score: unknown) => ({ source: 'bitman', version: 1, type: 'game-over', score })
const claimExpiresAt = '2099-10-24T12:00:00+00:00'

function makePending(overrides: Partial<PendingScore> = {}): PendingScore {
  return { id: '01jabcdefghjkmnpqrstv99999', token: 'b'.repeat(64), slug: 'snake', gameName: 'Snake', value: 1234, claimExpiresAt, ...overrides }
}

function setup(user: CurrentUser | null, path = '/games/snake') {
  const play = makeGameSession({ token: 'c'.repeat(64) })
  const guestScores: unknown[] = []
  const claims: unknown[] = []
  const session = sessionHandlers(user)
  server.use(
    ...session.handlers,
    leaderboardHandler([]),
    http.get('/api/v1/games/snake', () => HttpResponse.json({ data: game })),
    http.get('/api/v1/games', () => HttpResponse.json({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })),
    http.post('/api/v1/games/snake/guest-plays', () => HttpResponse.json({ data: play }, { status: 201 })),
    http.post('/api/v1/games/snake/sessions', () => HttpResponse.json({ data: makeGameSession() }, { status: 201 })),
    http.post('/api/v1/guest-plays/:id/score', async ({ request, params }) => {
      const body = (await request.json()) as { score: number }
      guestScores.push({ id: params.id, ...body })
      return HttpResponse.json({ data: { value: body.score, claim_expires_at: claimExpiresAt } }, { status: 201 })
    }),
    http.post('/api/v1/guest-plays/:id/claim', async ({ request, params }) => {
      claims.push({ id: params.id, ...((await request.json()) as object) })
      return HttpResponse.json(
        { data: { value: 1234, position: 3, personal_best: true, previous_best: null, game: { slug: 'snake', name: 'Snake' } } },
        { status: 201 },
      )
    }),
  )
  return { play, guestScores, claims, setUser: session.setUser, ...renderAt(path) }
}

describe('visitante', () => {
  it('joga numa partida de visitante e, no game-over, o Bitman avisa que não está logado e oferece o cadastro e o login', async () => {
    const { play, guestScores } = setup(null)
    const iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement
    expect(iframe).toHaveAttribute('src', play.play_url)

    postFromGame(iframe, gameOver(1234))

    const panel = await screen.findByRole('dialog', { name: 'Fim de jogo!' })
    expect(within(panel).getByLabelText('1.234 pontos')).toBeInTheDocument()
    expect(within(panel).getByText(/Você não está logado/)).toBeInTheDocument()
    expect(within(panel).getByRole('link', { name: 'Criar conta e salvar' })).toHaveAttribute('href', '/cadastro')
    expect(within(panel).getByRole('link', { name: 'Já tenho conta: entrar e salvar' })).toHaveAttribute('href', '/entrar?next=%2Fgames%2Fsnake')
    expect(guestScores).toEqual([{ id: play.id, score: 1234, token: play.token }])
  })

  it('guarda o score pendente neste navegador para salvar depois do login', async () => {
    const { play } = setup(null)
    postFromGame((await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement, gameOver(1234))
    await screen.findByRole('dialog', { name: 'Fim de jogo!' })

    expect(readPendingScore()).toEqual({ id: play.id, token: play.token, slug: 'snake', gameName: 'Snake', value: 1234, claimExpiresAt })
  })

  it('o CTA de entrar leva ao login e, ao entrar, o score é salvo e confirmado pelo Bitman', async () => {
    const { user, router, claims, setUser, play } = setup(null)
    postFromGame((await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement, gameOver(1234))
    await user.click(await screen.findByRole('link', { name: 'Já tenho conta: entrar e salvar' }))
    expect(router.state.location.pathname).toBe('/entrar')

    server.use(
      http.post('/api/v1/auth/login', () => {
        setUser(makeUser())
        return HttpResponse.json({ two_factor: false })
      }),
    )
    await user.type(screen.getByLabelText('E-mail'), 'ada@example.com')
    await user.type(screen.getByLabelText('Senha'), 'senha-123')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    const toast = await screen.findByText(/Score salvo! 1\.234 pontos em Snake · 3º lugar no ranking\./)
    expect(toast).toBeInTheDocument()
    expect(claims).toEqual([{ id: play.id, token: play.token }])
    await waitFor(() => expect(router.state.location.pathname).toBe('/games/snake'))
    expect(readPendingScore()).toBeNull()
    // De volta ao jogo, já logado: a partida passa a valer score.
    expect(await screen.findByText(/Partida valendo score/)).toBeInTheDocument()
  })

  it('e-mail não confirmado: o painel pede a confirmação, e o score não é salvo ainda', async () => {
    const { claims } = setup(makeUser({ email_verified: false }))
    postFromGame((await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement, gameOver(50))

    const panel = await screen.findByRole('dialog', { name: 'Fim de jogo!' })
    expect(within(panel).getByRole('link', { name: 'Confirmar e-mail e salvar' })).toHaveAttribute('href', '/verificar-email')
    expect(await screen.findByText(/Seu score de 50 em Snake fica guardado/)).toBeInTheDocument()
    expect(claims).toHaveLength(0)
  })

  it('score recusado pelo servidor (partida curta) mostra o motivo no painel', async () => {
    setup(null)
    server.use(
      http.post('/api/v1/guest-plays/:id/score', () =>
        HttpResponse.json({ message: 'A partida durou menos que o mínimo para registrar o score.' }, { status: 422 }),
      ),
    )
    postFromGame((await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement, gameOver(5))

    const panel = await screen.findByRole('dialog', { name: 'Score não guardado' })
    expect(within(panel).getByText('A partida durou menos que o mínimo para registrar o score.')).toBeInTheDocument()
    expect(readPendingScore()).toBeNull()
  })

  it('o painel pode ser fechado para ver a tela final do jogo', async () => {
    const { user } = setup(null)
    postFromGame((await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement, gameOver(5))
    await screen.findByRole('dialog', { name: 'Fim de jogo!' })

    await user.click(screen.getByRole('button', { name: 'Fechar' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('salvar o score pendente', () => {
  it('conta confirmada com score pendente: salva uma única vez, em qualquer página', async () => {
    savePendingScore(makePending())
    const { claims } = setup(makeUser(), '/')

    expect(await screen.findByText(/Score salvo! 1\.234 pontos em Snake/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver o ranking' })).toHaveAttribute('href', '/games/snake')
    expect(claims).toHaveLength(1)
  })

  it('recusa definitiva (ex.: já salvo) avisa e descarta o pendente', async () => {
    savePendingScore(makePending())
    setup(makeUser(), '/')
    server.use(http.post('/api/v1/guest-plays/:id/claim', () => HttpResponse.json({ message: 'Este score já foi salvo.' }, { status: 409 })))

    expect(await screen.findByRole('alert')).toHaveTextContent('Este score já foi salvo.')
    expect(readPendingScore()).toBeNull()
  })

  it('pendente vencido ou corrompido no storage é ignorado', async () => {
    savePendingScore(makePending({ claimExpiresAt: '2000-01-01T00:00:00+00:00' }))
    expect(readPendingScore()).toBeNull()

    localStorage.setItem('bitman.pending-score', '{"id": 1')
    expect(readPendingScore()).toBeNull()
  })
})
