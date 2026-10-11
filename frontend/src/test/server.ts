import { setupServer } from 'msw/node'

// Sem handlers globais: cada teste declara exatamente as respostas de que precisa.
export const server = setupServer()
