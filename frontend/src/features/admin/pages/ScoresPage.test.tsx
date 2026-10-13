import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import type { AdminScore } from '../scores'

function makeScore(overrides: Partial<AdminScore> = {}): AdminScore {
  return {
    id: '01jscore0000000000000000001',
    game: { id: '01jgame00000000000000000001', slug: 'snake', name: 'Snake' },
    player: { id: '01juser00000000000000000001', name: 'Ana' },
    version_number: 1,
    value: 999999,
    session_seconds: 42,
    flagged: true,
    flag_reason: 'Acima de 3 × o p99 do jogo (100, com 20 scores).',
    hidden: null,
    created_at: '2026-10-13T12:00:00+00:00',
    ...overrides,
  }
}

const page = (data: AdminScore[]) => HttpResponse.json({ data, meta: { current_page: 1, last_page: 1, total: data.length } })

describe('moderação de scores (S2)', () => {
  it('lista os sinalizados por padrão e oculta com motivo', async () => {
    const requests: string[] = []
    let hidden: unknown = null
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/scores', ({ request }) => {
        requests.push(new URL(request.url).search)
        return page(hidden ? [] : [makeScore()])
      }),
      http.post('/api/v1/admin/scores/:id/hide', async ({ request }) => {
        hidden = await request.json()
        return HttpResponse.json({ data: makeScore({ hidden: { by: 'Rafael', at: '2026-10-13T13:00:00+00:00', reason: 'Impossível.' } }) })
      }),
    )
    const { user } = renderAt('/admin/scores')

    const item = await screen.findByRole('listitem', { name: 'Score de Ana em Snake' })
    expect(item).toHaveTextContent('999.999')
    expect(item).toHaveTextContent('partida de 42s')
    expect(item).toHaveTextContent('Sinalizado: Acima de 3 × o p99 do jogo (100, com 20 scores).')
    expect(requests[0]).toBe('?filter=flagged&page=1')

    await user.click(within(item).getByRole('button', { name: 'Ocultar do ranking' }))
    await user.type(within(item).getByLabelText('Motivo'), 'Impossível.')
    await user.click(within(item).getByRole('button', { name: 'Confirmar ocultação' }))

    expect(await screen.findByText('Nenhum score aqui.')).toBeInTheDocument()
    expect(hidden).toEqual({ reason: 'Impossível.' })
  })

  it('na aba Ocultos, mostra quem ocultou e por quê, e devolve ao ranking', async () => {
    let restored = false
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/scores', ({ request }) => {
        expect(new URL(request.url).searchParams.get('filter')).toBe('hidden')
        return page(restored ? [] : [makeScore({ hidden: { by: 'Rafael', at: '2026-10-13T13:00:00+00:00', reason: 'Forjado.' } })])
      }),
      http.post('/api/v1/admin/scores/:id/restore', () => {
        restored = true
        return HttpResponse.json({ data: makeScore() })
      }),
    )
    const { user } = renderAt('/admin/scores?filtro=hidden')

    const item = await screen.findByRole('listitem', { name: 'Score de Ana em Snake' })
    expect(item).toHaveTextContent(/Oculto por Rafael em .*: Forjado\./)
    await user.click(within(item).getByRole('button', { name: 'Devolver ao ranking' }))

    expect(await screen.findByText('Nenhum score aqui.')).toBeInTheDocument()
  })
})
