import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeUser, sessionHandlers } from '../../../test/session'

async function fillForm(user: ReturnType<typeof renderAt>['user'], password = 'senha-forte-1', confirmation = password) {
  await user.type(await screen.findByLabelText('Nome'), 'Ada Lovelace')
  await user.type(screen.getByLabelText('E-mail'), 'ada@example.com')
  await user.type(screen.getByLabelText('Senha'), password)
  await user.type(screen.getByLabelText('Confirme a senha'), confirmation)
  await user.click(screen.getByRole('button', { name: 'Criar conta' }))
}

describe('RegisterPage', () => {
  it('cria a conta e orienta a confirmar o e-mail', async () => {
    const session = sessionHandlers(null)
    server.use(
      ...session.handlers,
      http.post('/api/v1/auth/register', () => {
        session.setUser(makeUser({ email_verified: false }))
        return new HttpResponse(null, { status: 201 })
      }),
    )
    const { user } = renderAt('/cadastro')

    await fillForm(user)

    expect(await screen.findByText(/enviamos um link de confirmação/i)).toBeInTheDocument()
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
  })

  it('mostra os erros de validação do servidor nos campos', async () => {
    server.use(
      ...sessionHandlers(null).handlers,
      http.post('/api/v1/auth/register', () =>
        HttpResponse.json({ message: 'x', errors: { email: ['O e-mail já está sendo utilizado.'] } }, { status: 422 }),
      ),
    )
    const { user } = renderAt('/cadastro')

    await fillForm(user)

    expect(await screen.findByText('O e-mail já está sendo utilizado.')).toBeInTheDocument()
  })

  it('confere senha e confirmação antes de enviar', async () => {
    server.use(...sessionHandlers(null).handlers)
    const { user } = renderAt('/cadastro')

    await fillForm(user, 'senha-forte-1', 'outra-senha-2')

    expect(await screen.findByText('As senhas não conferem.')).toBeInTheDocument()
  })
})
