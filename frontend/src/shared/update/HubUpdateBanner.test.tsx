import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { catalogHandler } from '../../test/games'
import { renderAt } from '../../test/render'
import { server } from '../../test/server'
import { sessionHandlers } from '../../test/session'

/* M10: uma aba aberta antes do deploy descobre que o hub mudou e oferece recarregar. */

const versionHandler = (build: string) => http.get('/version.json', () => HttpResponse.json({ build }))

describe('aviso de nova versão do hub (M10)', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('build publicado diferente do desta aba: mostra o aviso com "Recarregar"', async () => {
    vi.stubEnv('VITE_BITMAN_BUILD', 'aaaaaaa')
    server.use(...sessionHandlers(null).handlers, catalogHandler(), versionHandler('bbbbbbb'))
    renderAt('/')

    expect(await screen.findByText('Há uma nova versão do Bitman.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Recarregar' })).toBeInTheDocument()
  })

  it('mesmo build: nenhum aviso', async () => {
    vi.stubEnv('VITE_BITMAN_BUILD', 'aaaaaaa')
    const seen: string[] = []
    server.use(
      ...sessionHandlers(null).handlers,
      catalogHandler(),
      http.get('/version.json', ({ request }) => {
        seen.push(request.headers.get('cache-control') ?? '')
        return HttpResponse.json({ build: 'aaaaaaa' })
      }),
    )
    renderAt('/')

    await screen.findByRole('heading', { name: 'Bitman' })
    await vi.waitFor(() => expect(seen).toHaveLength(1))
    expect(screen.queryByText('Há uma nova versão do Bitman.')).not.toBeInTheDocument()
  })

  it('em desenvolvimento (sem build), não consulta nada', async () => {
    server.use(...sessionHandlers(null).handlers, catalogHandler())
    renderAt('/')

    expect(await screen.findByRole('heading', { name: 'Bitman' })).toBeInTheDocument()
    expect(screen.queryByText('Há uma nova versão do Bitman.')).not.toBeInTheDocument()
  })
})
