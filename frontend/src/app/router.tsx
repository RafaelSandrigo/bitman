import { createBrowserRouter, createMemoryRouter, type RouteObject } from 'react-router'
import { HomePage } from '../features/catalog/pages/HomePage'
import { NotFoundPage } from './NotFoundPage'
import { PublicLayout } from './PublicLayout'

const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]

export type AppRouter = ReturnType<typeof createBrowserRouter>

/** `memory` cria um roteador em memória (testes); sem ele, usa o histórico do navegador. */
export function createAppRouter(options: { memory?: string[] } = {}): AppRouter {
  return options.memory ? createMemoryRouter(routes, { initialEntries: options.memory }) : createBrowserRouter(routes)
}
