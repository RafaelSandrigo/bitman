import { screen } from '@testing-library/react'
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

    expect(await screen.findByText('Role concedida')).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Rafael' })).toBeInTheDocument()
    expect(screen.getByText('role: developer · via: panel')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Carregar mais' }))

    expect(await screen.findByText('Sistema (console)')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Carregar mais' })).not.toBeInTheDocument()
    expect(requests).toEqual(['subject_type=user&subject_id=U1', 'subject_type=user&subject_id=U1&cursor=c2'])
  })
})
