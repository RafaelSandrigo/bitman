import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import type { AuditEntry } from '../api'

const entry = (overrides: Partial<AuditEntry>): AuditEntry => ({
  id: 'E1',
  occurred_at: '2026-10-11T15:00:00+00:00',
  action: 'identity.role-granted',
  subject_type: 'user',
  subject_id: 'U1',
  context: { role: 'developer', via: 'panel' },
  reason: null,
  actor: { id: 'A1', name: 'Rafael' },
  ...overrides,
})

describe('AuditPage', () => {
  it('consulta o histórico da entidade indicada na URL e carrega mais pelo cursor', async () => {
    const requests: string[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/audit-entries', ({ request }) => {
        const params = new URL(request.url).searchParams
        requests.push(params.toString())
        return params.get('cursor')
          ? HttpResponse.json({ data: [entry({ id: 'E2', actor: null, context: { role: 'admin', via: 'console' } })], meta: { next_cursor: null } })
          : HttpResponse.json({ data: [entry({})], meta: { next_cursor: 'c2' } })
      }),
    )
    const { user } = renderAt('/admin/auditoria?subject_type=user&subject_id=U1')

    expect(await screen.findByRole('cell', { name: 'Role concedida' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Rafael' })).toBeInTheDocument()
    expect(screen.getByText('role: developer · via: panel')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Carregar mais' }))

    expect(await screen.findByText('Sistema (console)')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Carregar mais' })).not.toBeInTheDocument()
    expect(requests).toEqual(['subject_type=user&subject_id=U1', 'subject_type=user&subject_id=U1&cursor=c2'])
  })

  it('mostra a linha do tempo de um jogo e liga cada entrada à tela da entidade', async () => {
    const requests: string[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/audit-entries', ({ request }) => {
        requests.push(new URL(request.url).searchParams.toString())
        return HttpResponse.json({
          data: [
            entry({ id: 'E2', action: 'versioning.file-approved', subject_type: 'game_version', subject_id: 'V1', context: { path: 'index.html' } }),
            entry({ id: 'E1', action: 'catalog.game-submitted', subject_type: 'game', subject_id: 'G1', context: { slug: 'snake' } }),
          ],
          meta: { next_cursor: null },
        })
      }),
    )
    renderAt('/admin/auditoria?game_id=G1')

    expect(await screen.findByRole('heading', { name: 'Linha do tempo do jogo' })).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'Versão V1' })).toHaveAttribute('href', '/admin/versoes/V1')
    expect(screen.getByRole('link', { name: 'Jogo G1' })).toHaveAttribute('href', '/admin/jogos/G1')
    expect(requests).toEqual(['game_id=G1'])
  })

  it('mostra a linha do tempo de um usuário e filtra por ação', async () => {
    const requests: string[] = []
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      http.get('/api/v1/admin/audit-entries', ({ request }) => {
        requests.push(new URL(request.url).searchParams.toString())
        return HttpResponse.json({ data: [entry({})], meta: { next_cursor: null } })
      }),
    )
    const { user } = renderAt('/admin/auditoria?user_id=U1')

    expect(await screen.findByRole('heading', { name: 'Linha do tempo do usuário' })).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Ação'), 'versioning.version-rejected')
    await user.click(screen.getByRole('button', { name: 'Filtrar' }))

    await waitFor(() => expect(requests.at(-1)).toBe('action=versioning.version-rejected&user_id=U1'))
    expect(requests.at(-1)).toBe('action=versioning.version-rejected&user_id=U1')
  })
})
