import { afterEach, describe, expect, it, vi } from 'vitest'
import { listenToGame, parseGameMessage } from './bridge'

const message = (overrides: Record<string, unknown> = {}) => ({ source: 'bitman', version: 1, type: 'game-over', score: 10, ...overrides })

describe('parseGameMessage (protocolo do jogo)', () => {
  it('aceita ready e game-over no formato do protocolo', () => {
    expect(parseGameMessage(message({ type: 'ready', score: undefined }))).toEqual({ kind: 'ready' })
    expect(parseGameMessage(message())).toEqual({ kind: 'game-over', score: 10 })
    expect(parseGameMessage(message({ score: 0 }))).toEqual({ kind: 'game-over', score: 0 })
    expect(parseGameMessage(message({ score: Number.MAX_SAFE_INTEGER }))).toEqual({ kind: 'game-over', score: Number.MAX_SAFE_INTEGER })
  })

  it.each([
    ['negativo', -1],
    ['fração', 1.5],
    ['acima do MAX_SAFE_INTEGER', Number.MAX_SAFE_INTEGER + 1],
    ['string', '10'],
    ['NaN', Number.NaN],
    ['Infinity', Number.POSITIVE_INFINITY],
    ['ausente', undefined],
  ])('game-over com score inválido (%s) é rejeitado, nunca arredondado', (_, score) => {
    expect(parseGameMessage(message({ score }))).toEqual({ kind: 'invalid-score' })
  })

  it.each([
    ['outra fonte', message({ source: 'outro' })],
    ['outra versão', message({ version: 2 })],
    ['tipo desconhecido', message({ type: 'cheat' })],
    ['não objeto', 'game-over'],
    ['nulo', null],
  ])('mensagem fora do formato é ignorada (%s)', (_, data) => {
    expect(parseGameMessage(data)).toBeNull()
  })
})

describe('listenToGame (J4)', () => {
  let stop: (() => void) | undefined
  afterEach(() => stop?.())

  function setup() {
    const iframe = document.createElement('iframe')
    document.body.append(iframe)
    const onEvent = vi.fn()
    stop = listenToGame(() => iframe, onEvent)
    const send = (data: unknown, init: Partial<MessageEventInit> = {}) =>
      window.dispatchEvent(new MessageEvent('message', { data, origin: 'null', source: iframe.contentWindow, ...init }))
    return { iframe, onEvent, send }
  }

  it('entrega a mensagem vinda do iframe da partida', () => {
    const { onEvent, send } = setup()
    send(message({ type: 'ready' }))
    send(message({ score: 42 }))

    expect(onEvent.mock.calls).toEqual([[{ kind: 'ready' }], [{ kind: 'game-over', score: 42 }]])
  })

  it('ignora mensagens de outra janela ou com origem não opaca', () => {
    const { onEvent, send } = setup()
    send(message(), { source: window })
    send(message(), { source: null })
    send(message(), { origin: 'https://games.test' })

    expect(onEvent).not.toHaveBeenCalled()
  })

  it('só o primeiro game-over da partida é entregue', () => {
    const { onEvent, send } = setup()
    send(message({ score: 1 }))
    send(message({ score: 999 }))
    send(message({ score: 'x' }))

    expect(onEvent.mock.calls).toEqual([[{ kind: 'game-over', score: 1 }]])
  })

  it('um game-over inválido também encerra a partida', () => {
    const { onEvent, send } = setup()
    send(message({ score: 1.5 }))
    send(message({ score: 10 }))

    expect(onEvent.mock.calls).toEqual([[{ kind: 'invalid-score' }]])
  })

  it('para de escutar quando desligado', () => {
    const { onEvent, send } = setup()
    stop?.()
    send(message())

    expect(onEvent).not.toHaveBeenCalled()
  })
})
