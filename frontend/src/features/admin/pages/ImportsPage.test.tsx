import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { makeVersion } from '../../../test/versions'
import type { Version } from '../versions'

function page(versions: Version[]) {
  return { data: versions, meta: { current_page: 1, last_page: 1, total: versions.length } }
}

describe('ImportsPage', () => {
  it('mostra a fila do Portão 0 e autoriza a importação', async () => {
    let version = makeVersion()
    const statuses: (string | null)[] = []
    let authorized = false
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/versions', ({ request }) => {
        statuses.push(new URL(request.url).searchParams.get('status'))
        return HttpResponse.json(page(authorized ? [] : [version]))
      }),
      http.post(`/api/v1/admin/versions/${version.id}/authorize-import`, () => {
        authorized = true
        version = { ...version, status: 'fetching' }
        const { game: _game, requested_by: _by, ...data } = version
        return HttpResponse.json({ data })
      }),
    )
    const { user } = renderAt('/admin/importacoes')

    const item = await screen.findByRole('listitem', { name: 'Snake — versão 1' })
    expect(within(item).getByText(/Nada é buscado antes disso/)).toBeInTheDocument()
    expect(within(item).getByRole('link', { name: 'Detalhes' })).toHaveAttribute('href', `/admin/versoes/${version.id}`)

    await user.click(within(item).getByRole('button', { name: 'Autorizar importação' }))

    expect(await screen.findByText('Nenhuma versão nesta fila.')).toBeInTheDocument()
    expect(statuses[0]).toBe('import_requested')
  })

  it('rejeita com motivo e mostra o erro de validação do servidor', async () => {
    const version = makeVersion()
    const bodies: unknown[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/versions', () => HttpResponse.json(page([version]))),
      http.post(`/api/v1/admin/versions/${version.id}/reject-import`, async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json(
          { message: 'Informe o motivo, de 3 a 1000 caracteres.', errors: { reason: ['Informe o motivo, de 3 a 1000 caracteres.'] } },
          { status: 422 },
        )
      }),
    )
    const { user } = renderAt('/admin/importacoes')

    await user.click(await screen.findByRole('button', { name: 'Rejeitar' }))
    await user.type(screen.getByRole('textbox', { name: 'Motivo da rejeição' }), 'ok')
    await user.click(screen.getByRole('button', { name: 'Confirmar rejeição' }))

    expect(await screen.findByText('Informe o motivo, de 3 a 1000 caracteres.')).toBeInTheDocument()
    expect(bodies).toEqual([{ reason: 'ok' }])
  })

  it('na fila de falhas mostra o motivo e pede nova importação', async () => {
    const version = makeVersion({ status: 'fetch_failed', fetch_failure: { code: 'ref_not_found', message: 'A branch ou tag não existe no repositório.' } })
    let created = false
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/versions', ({ request }) =>
        HttpResponse.json(page(new URL(request.url).searchParams.get('status') === 'fetch_failed' ? [version] : [])),
      ),
      http.post(`/api/v1/admin/games/${version.game.id}/versions`, () => {
        created = true
        const { game: _game, requested_by: _by, ...data } = makeVersion({ number: 2 })
        return HttpResponse.json({ data }, { status: 201 })
      }),
    )
    const { user, router } = renderAt('/admin/importacoes')

    await user.click(await screen.findByRole('tab', { name: 'Busca falhou' }))
    expect(router.state.location.search).toBe('?estado=fetch_failed')
    expect(await screen.findByText('A branch ou tag não existe no repositório.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Pedir nova importação' }))

    expect(await screen.findByText('Nova versão criada. Ela aguarda autorização.')).toBeInTheDocument()
    expect(created).toBe(true)
  })

  it('o menu da administração leva às importações', async () => {
    server.use(...sessionHandlers(makeAdmin()).handlers, http.get('/api/v1/admin/submissions', () => HttpResponse.json({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })), http.get('/api/v1/admin/versions', () => HttpResponse.json(page([]))))
    const { user, router } = renderAt('/admin')

    await user.click(await screen.findByRole('link', { name: 'Importações' }))

    expect(router.state.location.pathname).toBe('/admin/importacoes')
    expect(await screen.findByRole('heading', { name: 'Importações' })).toBeInTheDocument()
  })
})
