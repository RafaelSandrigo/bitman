/**
 * Origem isolada dos jogos (ADR 0004), definida no build (`VITE_GAMES_ORIGIN`, do `GAMES_ORIGIN` do .env).
 * Vazia = nenhum jogo é carregado (falha fechada).
 */
export const gamesOrigin = (import.meta.env.VITE_GAMES_ORIGIN ?? '').replace(/\/+$/, '')

const playPath = /^\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\/index\.html$/

/**
 * Zero trust também na URL que a API devolve: só `{origem de jogos}/{UUID}/index.html`. Qualquer
 * outra coisa (outro host, outro caminho, query, fragmento) nunca vai para o iframe.
 */
export function isPlayUrl(value: string): boolean {
  if (gamesOrigin === '') return false
  try {
    const url = new URL(value)
    return url.origin === gamesOrigin && playPath.test(url.pathname) && url.search === '' && url.hash === '' && value === `${url.origin}${url.pathname}`
  } catch {
    return false
  }
}
