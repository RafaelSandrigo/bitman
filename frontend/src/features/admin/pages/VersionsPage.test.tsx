import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { makeVersion, queueHandlers } from '../../../test/versions'
import type { Version } from '../versions'

function page(versions: Version[]) {
  return { data: versions, meta: { current_page: 1, last_page: 1, total: versions.length } }
}

describe('VersionsPage', () => {
  it('mostra a fila do Portão 0 e autoriza a importação', async () => {
    let version = makeVersion()
    const statuses: (string | null)[] = []
    let authorized = false
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers,
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
    const { user } = renderAt('/admin/versoes')

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
      ...queueHandlers().handlers,
      http.get('/api/v1/admin/versions', () => HttpResponse.json(page([version]))),
      http.post(`/api/v1/admin/versions/${version.id}/reject-import`, async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json(
          { message: 'Informe o motivo, de 3 a 1000 caracteres.', errors: { reason: ['Informe o motivo, de 3 a 1000 caracteres.'] } },
          { status: 422 },
        )
      }),
    )
    const { user } = renderAt('/admin/versoes')

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
      ...queueHandlers().handlers,
      http.get('/api/v1/admin/versions', ({ request }) =>
        HttpResponse.json(page(new URL(request.url).searchParams.get('status') === 'fetch_failed' ? [version] : [])),
      ),
      http.post(`/api/v1/admin/games/${version.game.id}/versions`, () => {
        created = true
        const { game: _game, requested_by: _by, ...data } = makeVersion({ number: 2 })
        return HttpResponse.json({ data }, { status: 201 })
      }),
    )
    const { user, router } = renderAt('/admin/versoes')

    await user.click(await screen.findByRole('tab', { name: /^Busca falhou/ }))
    expect(router.state.location.search).toBe('?estado=fetch_failed')
    expect(await screen.findByText('A branch ou tag não existe no repositório.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Pedir nova importação' }))

    expect(await screen.findByText('Nova versão criada. Ela aguarda autorização.')).toBeInTheDocument()
    expect(created).toBe(true)
  })

  it('a fila do Portão 1 leva à revisão do commit', async () => {
    const version = makeVersion({ status: 'awaiting_commit_review', commit_sha: 'a'.repeat(40), blocked: false })
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers,
      http.get('/api/v1/admin/versions', ({ request }) =>
        HttpResponse.json(page(new URL(request.url).searchParams.get('status') === 'awaiting_commit_review' ? [version] : [])),
      ),
    )
    const { user } = renderAt('/admin/versoes')

    await user.click(await screen.findByRole('tab', { name: /^Aguardando revisão do commit/ }))

    expect(await screen.findByRole('link', { name: 'Revisar commit (Portão 1) →' })).toHaveAttribute('href', `/admin/versoes/${version.id}`)
  })

  it('o endereço antigo das importações leva às versões', async () => {
    server.use(...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers, http.get('/api/v1/admin/versions', () => HttpResponse.json(page([]))))
    const { router } = renderAt('/admin/importacoes')

    expect(await screen.findByRole('heading', { name: 'Versões' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/admin/versoes')
  })

  it('o menu da administração leva às versões', async () => {
    server.use(...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers, http.get('/api/v1/admin/submissions', () => HttpResponse.json({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })), http.get('/api/v1/admin/versions', () => HttpResponse.json(page([]))))
    const { user, router } = renderAt('/admin')

    await user.click(await screen.findByRole('link', { name: 'Versões' }))

    expect(router.state.location.pathname).toBe('/admin/versoes')
    expect(await screen.findByRole('heading', { name: 'Versões' })).toBeInTheDocument()
  })

  it('mostra o total de cada fila e a tag Novo nas filas com versões que nenhum admin viu', async () => {
    const queues = queueHandlers({ import_requested: { total: 3, new: 2 }, fetch_failed: { total: 1, new: 0 } })
    server.use(...sessionHandlers(makeAdmin()).handlers, ...queues.handlers, http.get('/api/v1/admin/versions', () => HttpResponse.json(page([]))))
    renderAt('/admin/versoes')

    const requested = await screen.findByRole('tab', { name: 'Aguardando autorização: 3 versões, 2 novas' })
    expect(within(requested).getByText('Novo')).toBeInTheDocument()
    expect(within(requested).getByText('3')).toBeInTheDocument()
    const failed = screen.getByRole('tab', { name: 'Busca falhou: 1 versão' })
    expect(within(failed).queryByText('Novo')).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Rejeitada: 0 versões' })).toBeInTheDocument()
  })

  it('marca a versão nova como vista ao exibi-la, mantendo a tag na tela', async () => {
    const fresh = makeVersion({ is_new: true })
    const old = makeVersion({ game: { id: 'g2', name: 'Tetris', slug: 'tetris' } })
    const queues = queueHandlers({ import_requested: { total: 2, new: 1 } })
    server.use(...sessionHandlers(makeAdmin()).handlers, ...queues.handlers, http.get('/api/v1/admin/versions', () => HttpResponse.json(page([fresh, old]))))
    renderAt('/admin/versoes')

    const item = await screen.findByRole('listitem', { name: 'Snake — versão 1' })
    expect(within(item).getByText('Novo')).toBeInTheDocument()
    expect(within(screen.getByRole('listitem', { name: 'Tetris — versão 1' })).queryByText('Novo')).not.toBeInTheDocument()
    await waitFor(() => expect(queues.seen).toEqual([{ versions: [{ id: fresh.id, status: 'import_requested' }] }]))
    expect(within(item).getByText('Novo')).toBeInTheDocument()
  })

  it('busca por jogo ou solicitante e filtra as prontas para publicar, pela URL', async () => {
    const requests: string[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers,
      http.get('/api/v1/admin/versions', ({ request }) => {
        requests.push(new URL(request.url).searchParams.toString())
        return HttpResponse.json(page([]))
      }),
    )
    const { user, router } = renderAt('/admin/versoes?estado=awaiting_file_review&prontas=1')

    expect(await screen.findByRole('checkbox', { name: 'Só as prontas para publicar' })).toBeChecked()
    await waitFor(() => expect(requests).toContain('status=awaiting_file_review&page=1&ready=1'))

    await user.click(screen.getByRole('checkbox', { name: 'Só as prontas para publicar' }))
    await user.type(screen.getByRole('searchbox', { name: 'Buscar por jogo ou solicitante' }), 'snake')
    await user.click(screen.getByRole('button', { name: 'Buscar' }))

    await waitFor(() => expect(requests.at(-1)).toBe('status=awaiting_file_review&page=1&search=snake'))
    expect(router.state.location.search).toBe('?estado=awaiting_file_review&busca=snake')
  })
})
