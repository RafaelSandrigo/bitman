import { z } from 'zod'

/** Erro de chamada à API: status HTTP (0 = resposta fora do contrato) e o corpo recebido. */
export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(message: string, status: number, body: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie'
/** Status do Laravel para token CSRF expirado ou ausente. */
const CSRF_MISMATCH = 419

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

/** Sanctum SPA: o cookie XSRF-TOKEN precisa existir antes de qualquer requisição que altera estado. */
async function refreshCsrfCookie(): Promise<void> {
  await fetch(CSRF_COOKIE_PATH, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
}

function send(method: Method, path: string, body: unknown): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  return fetch(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

async function request<T>(method: Method, path: string, schema: z.ZodType<T>, body?: unknown): Promise<T> {
  const mutating = method !== 'GET'
  if (mutating && !xsrfToken()) await refreshCsrfCookie()

  let response = await send(method, path, body)
  if (mutating && response.status === CSRF_MISMATCH) {
    await refreshCsrfCookie()
    response = await send(method, path, body)
  }

  const text = await response.text()
  let payload: unknown = null
  if (text !== '') {
    try {
      payload = JSON.parse(text)
    } catch {
      payload = null
    }
  }

  if (!response.ok) {
    throw new ApiError(`HTTP ${response.status} em ${method} ${path}`, response.status, payload)
  }

  // Zero trust: toda resposta passa pelo schema antes de chegar à aplicação.
  const parsed = schema.safeParse(payload)
  if (!parsed.success) {
    throw new ApiError(`Resposta fora do contrato em ${method} ${path}`, 0, payload)
  }

  return parsed.data
}

export function apiGet<T>(path: string, schema: z.ZodType<T>): Promise<T> {
  return request('GET', path, schema)
}

export function apiSend<T>(method: Exclude<Method, 'GET'>, path: string, schema: z.ZodType<T>, body?: unknown): Promise<T> {
  return request(method, path, schema, body)
}

/** Para respostas sem corpo útil (201/204 do Fortify). */
export const NoContent = z.unknown()

const ErrorBody = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
})

/** Erros de validação (422) por campo, ou `{}` quando o erro não é de validação. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.status !== 422) return {}
  const parsed = ErrorBody.safeParse(error.body)
  if (!parsed.success || !parsed.data.errors) return {}

  const result: Record<string, string> = {}
  for (const [field, messages] of Object.entries(parsed.data.errors)) {
    if (messages[0]) result[field] = messages[0]
  }
  return result
}

/** Mensagem para o usuário a partir de qualquer erro da API. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 429) return 'Muitas tentativas. Aguarde um minuto e tente de novo.'
    if (error.status === 403) return 'Você não tem permissão para esta ação.'
    const parsed = ErrorBody.safeParse(error.body)
    if (parsed.success && parsed.data.message && error.status >= 400 && error.status < 500) return parsed.data.message
  }
  return 'Algo deu errado. Tente novamente em instantes.'
}
