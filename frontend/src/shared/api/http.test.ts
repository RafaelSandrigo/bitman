import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { z } from 'zod'
import { server } from '../../test/server'
import { ApiError, apiGet, apiSend, errorMessage, fieldErrors, NoContent } from './http'

const Health = z.object({ status: z.literal('ok') })

describe('apiGet', () => {
  it('retorna o corpo validado pelo schema', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ status: 'ok' })))

    await expect(apiGet('/api/health', Health)).resolves.toEqual({ status: 'ok' })
  })

  it('rejeita resposta que não respeita o schema (zero trust na fronteira)', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ status: 'hacked', extra: 1 })))

    await expect(apiGet('/api/health', Health)).rejects.toBeInstanceOf(ApiError)
  })

  it('lança ApiError com o status HTTP em respostas de erro', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ message: 'x' }, { status: 403 })))

    await expect(apiGet('/api/health', Health)).rejects.toMatchObject({ status: 403 })
  })

  it('envia o token XSRF do cookie e mantém as credenciais na mesma origem', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D%3D'
    let seen: { xsrf: string | null; accept: string | null } | undefined
    server.use(
      http.get('/api/health', ({ request }) => {
        seen = { xsrf: request.headers.get('X-XSRF-TOKEN'), accept: request.headers.get('Accept') }
        return HttpResponse.json({ status: 'ok' })
      }),
    )

    await apiGet('/api/health', Health)

    expect(seen).toEqual({ xsrf: 'abc==', accept: 'application/json' })
  })
})

describe('apiSend', () => {
  it('busca o cookie CSRF do Sanctum antes da primeira requisição que altera estado', async () => {
    const calls: string[] = []
    server.use(
      http.get('/sanctum/csrf-cookie', () => {
        calls.push('csrf')
        document.cookie = 'XSRF-TOKEN=novo'
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('/api/v1/auth/logout', ({ request }) => {
        calls.push(`logout:${request.headers.get('X-XSRF-TOKEN')}`)
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await apiSend('POST', '/api/v1/auth/logout', NoContent)

    expect(calls).toEqual(['csrf', 'logout:novo'])
  })

  it('renova o cookie e repete uma vez quando o token CSRF expirou (419)', async () => {
    document.cookie = 'XSRF-TOKEN=velho'
    let attempts = 0
    server.use(
      http.get('/sanctum/csrf-cookie', () => {
        document.cookie = 'XSRF-TOKEN=novo'
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('/api/v1/auth/logout', ({ request }) => {
        attempts++
        return request.headers.get('X-XSRF-TOKEN') === 'novo'
          ? new HttpResponse(null, { status: 204 })
          : HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
      }),
    )

    await apiSend('POST', '/api/v1/auth/logout', NoContent)

    expect(attempts).toBe(2)
  })
})

describe('fieldErrors e errorMessage', () => {
  it('extrai a primeira mensagem de cada campo de um 422', () => {
    const error = new ApiError('x', 422, { message: 'inválido', errors: { email: ['E-mail inválido.', 'outro'] } })

    expect(fieldErrors(error)).toEqual({ email: 'E-mail inválido.' })
  })

  it('não confia em corpos fora do formato esperado', () => {
    expect(fieldErrors(new ApiError('x', 422, { errors: 'hacked' }))).toEqual({})
    expect(fieldErrors(new Error('rede'))).toEqual({})
  })

  it('traduz 429 e esconde detalhes de erros 5xx', () => {
    expect(errorMessage(new ApiError('x', 429, null))).toMatch(/muitas tentativas/i)
    expect(errorMessage(new ApiError('x', 500, { message: 'SQLSTATE...' }))).toMatch(/algo deu errado/i)
    expect(errorMessage(new ApiError('x', 422, { message: 'Regra violada.' }))).toBe('Regra violada.')
  })
})
