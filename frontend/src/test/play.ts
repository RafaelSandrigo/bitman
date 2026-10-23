import { http, HttpResponse } from 'msw'
import type { GameSession, LeaderboardEntry } from '../features/play/api'
import { ulid } from './session'

export function makeGameSession(overrides: Partial<GameSession> = {}): GameSession {
  return {
    id: ulid(),
    token: 'a'.repeat(64),
    play_url: 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html',
    started_at: '2026-10-13T12:00:00+00:00',
    expires_at: '2026-10-13T14:00:00+00:00',
    min_seconds: 5,
    ...overrides,
  }
}

export function makeLeaderboardEntry(overrides: Partial<LeaderboardEntry> = {}): LeaderboardEntry {
  return {
    position: 1,
    player_name: 'Ana',
    value: 1500,
    version_number: 1,
    achieved_at: '2026-10-13T12:00:00+00:00',
    is_me: false,
    ...overrides,
  }
}

export function leaderboardHandler(entries: LeaderboardEntry[] = [], slug = 'snake') {
  return http.get(`/api/v1/games/${slug}/leaderboard`, () => HttpResponse.json({ data: entries, meta: { game: { slug, name: 'Snake' } } }))
}

/** O jogo (dentro do iframe) manda uma mensagem ao hub. Origem opaca: `'null'`. */
export function postFromGame(iframe: HTMLIFrameElement, data: unknown, options: { origin?: string; source?: Window | null } = {}) {
  window.dispatchEvent(new MessageEvent('message', { data, origin: options.origin ?? 'null', source: options.source === undefined ? iframe.contentWindow : options.source }))
}

/** Sem conta, a página do jogo abre uma partida de visitante: este handler a atende. */
export function guestPlayHandler(slug = 'snake', play: GameSession = makeGameSession()) {
  return http.post(`/api/v1/games/${slug}/guest-plays`, () => HttpResponse.json({ data: play }, { status: 201 }))
}
