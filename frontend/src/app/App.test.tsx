import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { App } from './App'
import { createAppRouter } from './router'

function renderAt(path: string) {
  return render(<App router={createAppRouter({ memory: [path] })} />)
}

describe('App', () => {
  it('mostra a página inicial do Bitman', async () => {
    renderAt('/')

    expect(await screen.findByRole('heading', { level: 1, name: /bitman/i })).toBeInTheDocument()
  })

  it('mostra "página não encontrada" para rotas desconhecidas', async () => {
    renderAt('/rota-que-nao-existe')

    expect(await screen.findByRole('heading', { name: /página não encontrada/i })).toBeInTheDocument()
  })
})
