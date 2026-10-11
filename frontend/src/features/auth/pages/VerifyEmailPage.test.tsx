import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeUser, sessionHandlers } from '../../../test/session'

// Minúsculo, como o Laravel gera: o validador do link precisa aceitar.
const userId = '01m4p9zc21asj0dybfnyzpndr3'
const apiLink = `/api/v1/auth/email/verify/${userId}/${'a'.repeat(40)}?expires=1790000000&signature=${'b'.repeat(64)}`
const pageUrl = (link: string) => `/verificar-email?link=${encodeURIComponent(link)}`

describe('VerifyEmailPage', () => {
  it('confirma o e-mail chamando o link assinado na API', async () => {
    const session = sessionHandlers(makeUser({ id: userId, email_verified: false }))
    let called = false
    server.use(
      ...session.handlers,
      http.get(`/api/v1/auth/email/verify/${userId}/:hash`, () => {
        called = true
        session.setUser(makeUser({ id: userId, email_verified: true }))
        return new HttpResponse(null, { status: 204 })
      }),
    )
    renderAt(pageUrl(apiLink))

    expect(await screen.findByText(/e-mail confirmado/i)).toBeInTheDocument()
    expect(called).toBe(true)
  })

  it('não chama a API com um link fora do formato esperado', async () => {
    server.use(...sessionHandlers(makeUser({ email_verified: false })).handlers)
    renderAt(pageUrl(`/api/v1/admin/users/${userId}/developer`))

    expect(await screen.findByRole('alert')).toHaveTextContent(/inválido ou expirado/i)
  })

  it('informa link expirado ou de outra conta (403) e permite reenviar', async () => {
    let resent = false
    server.use(
      ...sessionHandlers(makeUser({ email_verified: false })).handlers,
      http.get(`/api/v1/auth/email/verify/${userId}/:hash`, () => HttpResponse.json({ message: 'Invalid signature.' }, { status: 403 })),
      http.post('/api/v1/auth/email/verification-notification', () => {
        resent = true
        return new HttpResponse(null, { status: 202 })
      }),
    )
    const { user } = renderAt(pageUrl(apiLink))

    expect(await screen.findByRole('alert')).toHaveTextContent(/inválido ou expirado/i)
    await user.click(screen.getByRole('button', { name: /reenviar/i }))

    expect(await screen.findByText(/enviamos um novo link/i)).toBeInTheDocument()
    expect(resent).toBe(true)
  })

  it('pede login ao visitante, voltando para o mesmo link depois', async () => {
    server.use(...sessionHandlers(null).handlers)
    renderAt(pageUrl(apiLink))

    const login = await screen.findByRole('link', { name: /entre na sua conta/i })
    expect(login.getAttribute('href')).toBe(`/entrar?next=${encodeURIComponent(pageUrl(apiLink))}`)
  })
})
