import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { catalogHandler } from '../../../test/games'
import { server } from '../../../test/server'
import { makeUser, sessionHandlers } from '../../../test/session'

function loginSucceeds() {
  const session = sessionHandlers(null)
  let body: unknown
  server.use(
    catalogHandler(),
    ...session.handlers,
    http.post('/api/v1/auth/login', async ({ request }) => {
      body = await request.json()
      session.setUser(makeUser())
      return HttpResponse.json({ two_factor: false })
    }),
  )
  return { sentBody: () => body }
}

describe('LoginPage', () => {
  it('entra e volta para o destino interno pedido em ?next=', async () => {
    const { sentBody } = loginSucceeds()
    const { user, router } = renderAt('/entrar?next=/verificar-email')

    await user.type(await screen.findByLabelText('E-mail'), 'ada@example.com')
    await user.type(screen.getByLabelText('Senha'), 'senha-forte-1')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('heading', { name: /confirmar e-mail/i })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/verificar-email')
    expect(sentBody()).toEqual({ email: 'ada@example.com', password: 'senha-forte-1' })
  })

  it('ignora destino externo em ?next= (sem redirecionamento aberto)', async () => {
    loginSucceeds()
    const { user, router } = renderAt('/entrar?next=//evil.example.com')

    await user.type(await screen.findByLabelText('E-mail'), 'ada@example.com')
    await user.type(screen.getByLabelText('Senha'), 'senha-forte-1')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('heading', { level: 1, name: /bitman/i })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })

  it('mostra o erro de credenciais no campo de e-mail', async () => {
    server.use(
      ...sessionHandlers(null).handlers,
      http.post('/api/v1/auth/login', () =>
        HttpResponse.json(
          { message: 'Credenciais inválidas.', errors: { email: ['Credenciais inválidas.'] } },
          { status: 422 },
        ),
      ),
    )
    const { user } = renderAt('/entrar')

    await user.type(await screen.findByLabelText('E-mail'), 'ada@example.com')
    await user.type(screen.getByLabelText('Senha'), 'errada')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('Credenciais inválidas.')).toBeInTheDocument()
    expect(screen.getByLabelText('E-mail')).toHaveAttribute('aria-invalid', 'true')
  })

  it('avisa quando o limite de tentativas é atingido (A2)', async () => {
    server.use(
      ...sessionHandlers(null).handlers,
      http.post('/api/v1/auth/login', () => HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })),
    )
    const { user } = renderAt('/entrar')

    await user.type(await screen.findByLabelText('E-mail'), 'ada@example.com')
    await user.type(screen.getByLabelText('Senha'), 'qualquer')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(/muitas tentativas/i)
  })

  it('valida o formulário antes de chamar a API', async () => {
    server.use(...sessionHandlers(null).handlers)
    const { user } = renderAt('/entrar')

    await user.click(await screen.findByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('Informe um e-mail válido.')).toBeInTheDocument()
    expect(screen.getByText('Informe a senha.')).toBeInTheDocument()
  })

  it('quem já está logado não vê o login', async () => {
    server.use(catalogHandler(), ...sessionHandlers(makeUser()).handlers)
    const { router } = renderAt('/entrar')

    expect(await screen.findByRole('heading', { level: 1, name: /bitman/i })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })
})
