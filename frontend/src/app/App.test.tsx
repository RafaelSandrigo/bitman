import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { catalogHandler } from '../test/games'
import { renderAt } from '../test/render'
import { server } from '../test/server'
import { makeAdmin, makeUser, sessionHandlers } from '../test/session'
import { queueHandlers } from '../test/versions'

describe('App', () => {
  it('mostra a página inicial do Bitman', async () => {
    server.use(catalogHandler(), ...sessionHandlers(null).handlers)
    renderAt('/')

    expect(await screen.findByRole('heading', { level: 1, name: /bitman/i })).toBeInTheDocument()
  })

  it('mostra "página não encontrada" para rotas desconhecidas', async () => {
    server.use(catalogHandler(), ...sessionHandlers(null).handlers)
    renderAt('/rota-que-nao-existe')

    expect(await screen.findByRole('heading', { name: /página não encontrada/i })).toBeInTheDocument()
  })

  it('visitante vê os links de entrar e criar conta', async () => {
    server.use(catalogHandler(), ...sessionHandlers(null).handlers)
    renderAt('/')

    expect(await screen.findByRole('link', { name: 'Entrar' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Criar conta' })).toBeInTheDocument()
  })

  it('avisa quem ainda não confirmou o e-mail', async () => {
    server.use(catalogHandler(), ...sessionHandlers(makeUser({ email_verified: false })).handlers)
    renderAt('/')

    expect(await screen.findByText(/confirme seu e-mail para jogar/i)).toBeInTheDocument()
  })

  it('sai da conta e volta a ser visitante', async () => {
    const session = sessionHandlers(makeUser())
    server.use(
      catalogHandler(),
      ...session.handlers,
      http.post('/api/v1/auth/logout', () => {
        session.setUser(null)
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { user } = renderAt('/')

    await user.click(await screen.findByRole('button', { name: 'Sair' }))

    expect(await screen.findByRole('link', { name: 'Entrar' })).toBeInTheDocument()
  })
})

describe('área admin (só interface: quem autoriza é a API)', () => {
  it('manda o visitante para o login, preservando o destino', async () => {
    server.use(catalogHandler(), ...sessionHandlers(null).handlers)
    const { router } = renderAt('/admin/auditoria')

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument()
    expect(router.state.location.search).toBe('?next=%2Fadmin%2Fauditoria')
  })

  it('nega acesso a quem não é admin', async () => {
    server.use(catalogHandler(), ...sessionHandlers(makeUser({ roles: ['player', 'developer'] })).handlers)
    renderAt('/admin')

    expect(await screen.findByRole('heading', { name: /acesso negado/i })).toBeInTheDocument()
  })

  it('mostra o link Admin e o painel para o admin', async () => {
    server.use(
      catalogHandler(),
      ...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers().handlers,
    )
    renderAt('/admin')

    expect(await screen.findByRole('heading', { name: 'Painel' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Admin' })).toBeInTheDocument()
  })
})
