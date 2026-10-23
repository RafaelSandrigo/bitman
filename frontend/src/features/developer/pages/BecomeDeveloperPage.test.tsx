import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeDeveloper, makeUser, sessionHandlers } from '../../../test/session'
import type { DeveloperRequest } from '../api'

function makeRequest(overrides: Partial<DeveloperRequest> = {}): DeveloperRequest {
  return {
    id: '01jdevreq00000000000000001',
    name: 'Ana Souza',
    github_url: 'https://github.com/ana',
    bio: 'Faço jogos em canvas há 3 anos.',
    status: 'pending',
    decided_by: null,
    decided_at: null,
    decision_reason: null,
    created_at: '2026-10-23T12:00:00+00:00',
    ...overrides,
  }
}

function setup(current: DeveloperRequest | null, user = makeUser()) {
  const sent: unknown[] = []
  server.use(
    ...sessionHandlers(user).handlers,
    http.get('/api/v1/me/developer-request', () => HttpResponse.json({ data: current })),
    http.post('/api/v1/me/developer-request', async ({ request }) => {
      const body = (await request.json()) as Record<string, string>
      sent.push(body)
      return HttpResponse.json({ data: makeRequest({ name: body.name, github_url: body.github_url, bio: body.bio }) }, { status: 201 })
    }),
  )
  return { sent, ...renderAt('/seja-desenvolvedor') }
}

describe('Seja Desenvolvedor (M18)', () => {
  it('envia o pedido com nome, GitHub e apresentação e mostra que está em análise', async () => {
    const { user, sent } = setup(null)

    await user.type(await screen.findByLabelText('Nome'), 'Ana Souza')
    await user.type(screen.getByLabelText('Perfil no GitHub'), 'https://github.com/ana')
    await user.type(screen.getByLabelText('Apresentação'), 'Faço jogos em canvas há 3 anos.')
    await user.click(screen.getByRole('button', { name: 'Enviar pedido' }))

    expect(await screen.findByText('Seu pedido está em análise.')).toBeInTheDocument()
    expect(sent).toEqual([{ name: 'Ana Souza', github_url: 'https://github.com/ana', bio: 'Faço jogos em canvas há 3 anos.' }])
  })

  it('valida o perfil do GitHub antes de enviar', async () => {
    const { user, sent } = setup(null)

    await user.type(await screen.findByLabelText('Nome'), 'Ana')
    await user.type(screen.getByLabelText('Perfil no GitHub'), 'https://gitlab.com/ana')
    await user.type(screen.getByLabelText('Apresentação'), 'Faço jogos em canvas há 3 anos.')
    await user.click(screen.getByRole('button', { name: 'Enviar pedido' }))

    expect(await screen.findByText('Use o endereço do seu perfil: https://github.com/seu-usuario.')).toBeInTheDocument()
    expect(sent).toHaveLength(0)
  })

  it('rejeitado: mostra o motivo e deixa pedir de novo', async () => {
    setup(makeRequest({ status: 'rejected', decision_reason: 'Mande um link para um jogo seu.' }))

    expect(await screen.findByText(/Mande um link para um jogo seu\./)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enviar pedido' })).toBeInTheDocument()
  })

  it('a apresentação é exibida como texto, nunca como HTML', async () => {
    setup(makeRequest({ bio: '<img src=x onerror=alert(1)>' }))

    expect(await screen.findByText('<img src=x onerror=alert(1)>')).toBeInTheDocument()
    expect(document.querySelector('img[src="x"]')).toBeNull()
  })

  it('quem já é developer é levado ao envio de jogos', async () => {
    setup(null, makeDeveloper())

    expect(await screen.findByText('Você já é developer.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Enviar um jogo' })).toHaveAttribute('href', '/desenvolvedor/enviar')
  })

  it('e-mail não confirmado precisa confirmar antes', async () => {
    setup(null, makeUser({ email_verified: false }))

    expect(await screen.findByText(/Confirme seu e-mail para pedir/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Enviar pedido' })).not.toBeInTheDocument()
  })
})
