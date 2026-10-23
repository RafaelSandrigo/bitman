import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import type { AdminDeveloperRequest } from '../developerRequests'

function makeRequest(overrides: Partial<AdminDeveloperRequest> = {}): AdminDeveloperRequest {
  return {
    id: '01jdevreq00000000000000001',
    user: { id: '01juser00000000000000000001', name: 'Ana', email: 'ana@example.com' },
    name: 'Ana Souza',
    github_url: 'https://github.com/ana',
    bio: 'Faço jogos em canvas.\nQuero publicar um Snake.',
    status: 'pending',
    decided_by: null,
    decided_at: null,
    decision_reason: null,
    created_at: '2026-10-23T12:00:00+00:00',
    ...overrides,
  }
}

const page = (data: AdminDeveloperRequest[]) => HttpResponse.json({ data, meta: { current_page: 1, last_page: 1, total: data.length } })

describe('pedidos de developer (M18)', () => {
  it('lista os pendentes com nome, GitHub e apresentação, e aprova', async () => {
    let approved = false
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/developer-requests', () => page(approved ? [] : [makeRequest()])),
      http.post('/api/v1/admin/developer-requests/:id/approve', () => {
        approved = true
        return HttpResponse.json({ data: makeRequest({ status: 'approved', decided_by: 'Rafael' }) })
      }),
    )
    const { user } = renderAt('/admin/desenvolvedores')

    const item = await screen.findByRole('listitem', { name: 'Pedido de Ana' })
    expect(within(item).getByRole('link', { name: 'https://github.com/ana' })).toHaveAttribute('rel', 'noopener noreferrer')
    expect(item).toHaveTextContent('Quero publicar um Snake.')
    await user.click(within(item).getByRole('button', { name: 'Aprovar como developer' }))

    expect(await screen.findByText('Nenhum pedido aqui.')).toBeInTheDocument()
    expect(approved).toBe(true)
  })

  it('rejeita com motivo', async () => {
    let body: unknown = null
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/developer-requests', () => page(body ? [] : [makeRequest()])),
      http.post('/api/v1/admin/developer-requests/:id/reject', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: makeRequest({ status: 'rejected', decision_reason: 'Sem jogo.' }) })
      }),
    )
    const { user } = renderAt('/admin/desenvolvedores')

    const item = await screen.findByRole('listitem', { name: 'Pedido de Ana' })
    await user.click(within(item).getByRole('button', { name: 'Rejeitar' }))
    await user.type(within(item).getByLabelText('Motivo da rejeição'), 'Sem jogo.')
    await user.click(within(item).getByRole('button', { name: 'Confirmar rejeição' }))

    expect(await screen.findByText('Nenhum pedido aqui.')).toBeInTheDocument()
    expect(body).toEqual({ reason: 'Sem jogo.' })
  })

  it('um link que não é perfil do GitHub não vira link', async () => {
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/developer-requests', () => page([makeRequest({ github_url: 'javascript:alert(1)' })])),
    )
    renderAt('/admin/desenvolvedores')

    const item = await screen.findByRole('listitem', { name: 'Pedido de Ana' })
    expect(within(item).queryByRole('link')).toBeNull()
    expect(item).toHaveTextContent('javascript:alert(1)')
  })
})
