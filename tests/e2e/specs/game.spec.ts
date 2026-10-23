import { expect, test, type Page } from '@playwright/test'

/*
| M11: o fluxo crítico num navegador real — o hub carrega o jogo no iframe isolado (J1, J3), com
| os headers de segurança (J2), e a bridge postMessage leva o score até o painel do Bitman (J4).
*/

const gamesUrl = process.env.GAMES_URL ?? 'http://games.e2e'
const password = process.env.E2E_PASSWORD ?? ''

async function openGame(page: Page) {
  await page.goto('/games/e2e')
  const iframe = page.locator('iframe[title="Jogo: Jogo E2E"]')
  await expect(iframe).toBeVisible()
  return iframe
}

test('o hub responde com CSP que só deixa enquadrar a origem de jogos', async ({ request }) => {
  const response = await request.get('/')
  const csp = response.headers()['content-security-policy'] ?? ''

  expect(csp).toContain(`frame-src ${gamesUrl}`)
  expect(csp).toContain("frame-ancestors 'none'")
  expect(response.headers()['x-content-type-options']).toBe('nosniff')
})

test('J1/J3: o jogo roda no iframe sandbox da origem de jogos, sem acesso ao hub, a cookies ou storage', async ({ page }) => {
  const iframe = await openGame(page)

  await expect(iframe).toHaveAttribute('sandbox', 'allow-scripts')
  expect(await iframe.getAttribute('src')).toMatch(new RegExp(`^${gamesUrl}/[0-9a-f-]{36}/index\\.html$`))
  await expect(page.frameLocator('iframe[title="Jogo: Jogo E2E"]').locator('#isolation')).toHaveText('isolado: sim')
})

test('J2: o arquivo do jogo vem com CSP sandbox, sem cache e sem transformação', async ({ page, request }) => {
  const iframe = await openGame(page)
  const response = await request.get((await iframe.getAttribute('src')) ?? '')

  expect(response.headers()['content-security-policy']).toContain('sandbox allow-scripts')
  expect(response.headers()['cache-control']).toContain('no-store')
  expect(response.headers()['cache-control']).toContain('no-transform')
})

test('visitante: o game-over abre o painel do Bitman com o score e o convite para entrar e salvar', async ({ page }) => {
  await openGame(page)
  await page.frameLocator('iframe[title="Jogo: Jogo E2E"]').getByRole('button', { name: 'Terminar' }).click()

  const panel = page.getByRole('dialog', { name: 'Fim de jogo!' })
  await expect(panel).toBeVisible()
  await expect(panel.getByLabel('42 pontos')).toBeVisible()
  await expect(panel.getByText(/Você não está logado/)).toBeVisible()
  await expect(panel.getByRole('link', { name: 'Criar conta e salvar' })).toBeVisible()
})

test('visitante entra depois de jogar: o score pendente é salvo e o Bitman confirma', async ({ page }) => {
  await openGame(page)
  await page.frameLocator('iframe[title="Jogo: Jogo E2E"]').getByRole('button', { name: 'Terminar' }).click()
  await page.getByRole('link', { name: 'Já tenho conta: entrar e salvar' }).click()

  await page.getByLabel('E-mail').fill('player@e2e.test')
  await page.getByLabel('Senha').fill(password)
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page.getByText(/Score salvo! 42 pontos em Jogo E2E/)).toBeVisible()
  await expect(page.getByRole('region', { name: 'Ranking' }).getByText('Jogadora E2E')).toBeVisible()
})
