import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makePublicGame } from '../../test/games'
import { leaderboardHandler, makeGameSession, makeLeaderboardEntry, postFromGame } from '../../test/play'
import { renderAt } from '../../test/render'
import { server } from '../../test/server'
import { makeUser, sessionHandlers } from '../../test/session'
import type { CurrentUser } from '../auth/api'
import type { GameSession } from './api'

const game = makePublicGame()
const gameOver = (score: unknown) => ({ source: 'bitman', version: 1, type: 'game-over', score })

/** Página /games/snake com o usuário dado; cada POST de sessão devolve a próxima da lista. */
function setup(user: CurrentUser | null, sessions: GameSession[] = [makeGameSession()]) {
  const started: GameSession[] = []
  const scores: unknown[] = []
  server.use(
    ...sessionHandlers(user).handlers,
    leaderboardHandler([]),
    http.get('/api/v1/games/snake', () => HttpResponse.json({ data: game })),
    http.post('/api/v1/games/snake/sessions', () => {
      const session = sessions[started.length] ?? sessions.at(-1)!
      started.push(session)
      return HttpResponse.json({ data: session }, { status: 201 })
    }),
    http.post('/api/v1/game-sessions/:id/score', async ({ request, params }) => {
      scores.push({ id: params.id, ...((await request.json()) as object) })
      return HttpResponse.json({ data: { value: 1234, position: 2, personal_best: true, previous_best: null } }, { status: 201 })
    }),
  )
  return { started, scores, ...renderAt('/games/snake') }
}

describe('partida valendo score', () => {
  it('visitante joga sem sessão: iframe da versão no ar e convite para entrar', async () => {
    const { started } = setup(null)

    const iframe = await screen.findByTitle('Jogo: Snake')
    expect(iframe).toHaveAttribute('src', game.play_url)
    expect(screen.getByText(/jogando sem registrar score/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Entre' })).toHaveAttribute('href', '/entrar?next=%2Fgames%2Fsnake')
    expect(started).toHaveLength(0)
  })

  it('e-mail não confirmado joga sem sessão (A5)', async () => {
    const { started } = setup(makeUser({ email_verified: false }))

    await screen.findByTitle('Jogo: Snake')
    expect(screen.getByText(/Confirme seu e-mail para registrar seus scores/)).toBeInTheDocument()
    expect(started).toHaveLength(0)
  })

  it('com conta confirmada, abre uma sessão e carrega a URL dela no iframe sandbox', async () => {
    const session = makeGameSession({ play_url: 'https://games.test/11111111-2222-4333-8444-555555555555/index.html' })
    const { started } = setup(makeUser(), [session])

    const iframe = await screen.findByTitle('Jogo: Snake')
    expect(iframe).toHaveAttribute('src', session.play_url)
    expect(iframe.getAttribute('sandbox')).toBe('allow-scripts')
    expect(started).toHaveLength(1)
    expect(screen.getByText(/Partida valendo score/)).toBeInTheDocument()
  })

  it('no game-over, envia o score com o token da sessão e mostra a posição', async () => {
    const session = makeGameSession()
    const { scores } = setup(makeUser(), [session])
    const iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement

    postFromGame(iframe, gameOver(1234))

    expect(await screen.findByText(/Score 1\.234 registrado · 2º lugar no ranking\. Novo recorde pessoal!/)).toBeInTheDocument()
    expect(scores).toEqual([{ id: session.id, score: 1234, token: session.token }])
  })

  it('J4: ignora mensagens forjadas (outra janela ou origem) e aceita só um game-over por partida', async () => {
    const { scores } = setup(makeUser())
    const iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement

    postFromGame(iframe, gameOver(5), { source: window })
    postFromGame(iframe, gameOver(5), { origin: 'https://evil.test' })
    postFromGame(iframe, { type: 'game-over', score: 5 })
    postFromGame(iframe, gameOver(10))
    postFromGame(iframe, gameOver(99999))

    await screen.findByText(/registrado/)
    expect(scores).toHaveLength(1)
    expect(scores[0]).toMatchObject({ score: 10 })
  })

  it('score inválido do jogo é rejeitado no hub: nada é enviado', async () => {
    const { scores } = setup(makeUser())
    const iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement

    postFromGame(iframe, gameOver(1.5))

    expect(await screen.findByText('O jogo enviou um score inválido; nada foi registrado.')).toBeInTheDocument()
    expect(scores).toHaveLength(0)
  })

  it('mostra o erro da API (ex.: replay recusado)', async () => {
    setup(makeUser())
    server.use(
      http.post('/api/v1/game-sessions/:id/score', () =>
        HttpResponse.json({ message: 'Esta partida já registrou um score. Inicie outra para jogar de novo.' }, { status: 409 }),
      ),
    )
    const iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement

    postFromGame(iframe, gameOver(10))

    expect(await screen.findByText('Esta partida já registrou um score. Inicie outra para jogar de novo.')).toBeInTheDocument()
  })

  it('"Jogar de novo" abre outra sessão e recarrega o jogo; o novo game-over vai para a nova sessão', async () => {
    const first = makeGameSession()
    const second = makeGameSession({ play_url: 'https://games.test/11111111-2222-4333-8444-555555555555/index.html' })
    const { user, started, scores } = setup(makeUser(), [first, second])
    let iframe = (await screen.findByTitle('Jogo: Snake')) as HTMLIFrameElement
    postFromGame(iframe, gameOver(1))
    await screen.findByText(/registrado/)

    await user.click(screen.getByRole('button', { name: 'Jogar de novo' }))

    await waitFor(() => expect(screen.getByTitle('Jogo: Snake')).toHaveAttribute('src', second.play_url))
    expect(screen.queryByText(/registrado/)).not.toBeInTheDocument()
    iframe = screen.getByTitle('Jogo: Snake') as HTMLIFrameElement
    postFromGame(iframe, gameOver(2))
    await screen.findByText(/registrado/)
    expect(started).toEqual([first, second])
    expect(scores).toEqual([
      { id: first.id, score: 1, token: first.token },
      { id: second.id, score: 2, token: second.token },
    ])
  })

  it('não usa uma play_url de sessão fora do formato esperado', async () => {
    const { container } = setup(makeUser(), [makeGameSession({ play_url: 'https://evil.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html' })])

    expect(await screen.findByText('Não foi possível carregar o jogo com segurança.')).toBeInTheDocument()
    expect(container.querySelector('iframe')).toBeNull()
  })

  it('falha ao abrir a sessão mostra o motivo e permite tentar de novo', async () => {
    setup(makeUser())
    server.use(http.post('/api/v1/games/snake/sessions', () => HttpResponse.json({ message: 'Este jogo não está disponível para partidas no momento.' }, { status: 422 })))

    expect(await screen.findByText('Este jogo não está disponível para partidas no momento.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).toBeInTheDocument()
  })
})

describe('ranking', () => {
  it('mostra posição, jogador, score, versão e destaca o próprio jogador, com a limitação explícita', async () => {
    server.use(
      ...sessionHandlers(null).handlers,
      http.get('/api/v1/games/snake', () => HttpResponse.json({ data: game })),
      leaderboardHandler([
        makeLeaderboardEntry({ position: 1, player_name: 'Ana', value: 9_007_199_254_740_991, version_number: 2 }),
        makeLeaderboardEntry({ position: 1, player_name: 'Bia', value: 9_007_199_254_740_991, version_number: 1, is_me: true }),
        makeLeaderboardEntry({ position: 3, player_name: 'Caio', value: 10, version_number: 1 }),
      ]),
    )
    renderAt('/games/snake')

    const ranking = await screen.findByRole('region', { name: 'Ranking' })
    const rows = await within(ranking).findAllByRole('row')
    expect(rows.slice(1).map((row) => row.textContent)).toEqual([
      expect.stringContaining('1ºAna9.007.199.254.740.991v2'),
      expect.stringContaining('1ºBia(você)9.007.199.254.740.991v1'),
      expect.stringContaining('3ºCaio10v1'),
    ])
    expect(within(ranking).getByText(/podem ser manipulados/)).toBeInTheDocument()
  })

  it('sem scores, convida a jogar', async () => {
    server.use(...sessionHandlers(null).handlers, http.get('/api/v1/games/snake', () => HttpResponse.json({ data: game })), leaderboardHandler([]))
    renderAt('/games/snake')

    expect(await screen.findByText('Ninguém pontuou ainda. Seja o primeiro!')).toBeInTheDocument()
  })
})
