import { describe, expect, it } from 'vitest'
import { gamesOrigin, isPlayUrl } from './config'

describe('isPlayUrl', () => {
  it('a origem de jogos vem do build', () => {
    expect(gamesOrigin).toBe('https://games.test')
  })

  it('aceita só {origem de jogos}/{UUID}/index.html', () => {
    expect(isPlayUrl('https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html')).toBe(true)
  })

  it.each([
    ['outro host', 'https://evil.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html'],
    ['o próprio hub', 'http://localhost/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html'],
    ['http', 'http://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html'],
    ['javascript:', 'javascript:alert(1)'],
    ['data:', 'data:text/html,<script>alert(1)</script>'],
    ['outro arquivo', 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/game.js'],
    ['traversal', 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/../index.html'],
    ['query', 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html?x=1'],
    ['fragmento', 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html#x'],
    ['credenciais', 'https://a:b@games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html'],
    ['UUID em maiúsculas', 'https://games.test/0B9F6A52-3C1E-4D7A-9B2E-5F8C1A2D3E4F/index.html'],
    ['vazio', ''],
  ])('recusa %s', (_, url) => {
    expect(isPlayUrl(url)).toBe(false)
  })
})
