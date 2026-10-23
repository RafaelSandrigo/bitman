import { createBrowserRouter, createMemoryRouter, Navigate, type RouteObject } from 'react-router'
import { AdminLayout } from '../features/admin/AdminLayout'
import { AdminGamePage } from '../features/admin/pages/AdminGamePage'
import { AuditPage } from '../features/admin/pages/AuditPage'
import { DashboardPage } from '../features/admin/pages/DashboardPage'
import { DeveloperRequestsPage } from '../features/admin/pages/DeveloperRequestsPage'
import { GamesPage } from '../features/admin/pages/GamesPage'
import { ScoresPage } from '../features/admin/pages/ScoresPage'
import { SubmissionsPage } from '../features/admin/pages/SubmissionsPage'
import { UsersPage } from '../features/admin/pages/UsersPage'
import { VersionPage } from '../features/admin/pages/VersionPage'
import { VersionsPage } from '../features/admin/pages/VersionsPage'
import { Permission } from '../features/auth/api'
import { ForgotPasswordPage } from '../features/auth/pages/ForgotPasswordPage'
import { LoginPage } from '../features/auth/pages/LoginPage'
import { RegisterPage } from '../features/auth/pages/RegisterPage'
import { ResetPasswordPage } from '../features/auth/pages/ResetPasswordPage'
import { VerifyEmailPage } from '../features/auth/pages/VerifyEmailPage'
import { GamePage } from '../features/catalog/pages/GamePage'
import { BecomeDeveloperPage } from '../features/developer/pages/BecomeDeveloperPage'
import { HomePage } from '../features/catalog/pages/HomePage'
import { MyScoresPage } from '../features/play/pages/MyScoresPage'
import { MyGamesPage } from '../features/submissions/pages/MyGamesPage'
import { SubmitGamePage } from '../features/submissions/pages/SubmitGamePage'
import { AdminGate, GuestOnly, RequireAuth, RequirePermission } from './guards'
import { NotFoundPage } from './NotFoundPage'
import { PublicLayout } from './PublicLayout'

const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'games/:slug', element: <GamePage /> },
      {
        element: <GuestOnly />,
        children: [
          { path: 'entrar', element: <LoginPage /> },
          { path: 'esqueci-a-senha', element: <ForgotPasswordPage /> },
          { path: 'redefinir-senha', element: <ResetPasswordPage /> },
        ],
      },
      { element: <GuestOnly home="/verificar-email" />, children: [{ path: 'cadastro', element: <RegisterPage /> }] },
      { path: 'verificar-email', element: <VerifyEmailPage /> },
      {
        element: <RequireAuth />,
        children: [
          { path: 'minhas-partidas', element: <MyScoresPage /> },
          { path: 'seja-desenvolvedor', element: <BecomeDeveloperPage /> },
        ],
      },
      {
        path: 'desenvolvedor',
        element: (
          <RequirePermission
            permission={Permission.SubmitGames}
            deniedMessage="Esta área é para developers. Peça a um administrador para liberar o envio de jogos na sua conta."
          />
        ),
        children: [
          { index: true, element: <Navigate to="jogos" replace /> },
          { path: 'jogos', element: <MyGamesPage /> },
          { path: 'enviar', element: <SubmitGamePage /> },
        ],
      },
      {
        path: 'admin',
        element: <AdminGate />,
        children: [
          {
            element: <AdminLayout />,
            children: [
              { index: true, element: <DashboardPage /> },
              { path: 'submissoes', element: <SubmissionsPage /> },
              { path: 'versoes', element: <VersionsPage /> },
              { path: 'importacoes', element: <Navigate to="/admin/versoes" replace /> },
              { path: 'versoes/:id', element: <VersionPage /> },
              { path: 'jogos', element: <GamesPage /> },
              { path: 'jogos/:id', element: <AdminGamePage /> },
              { path: 'scores', element: <ScoresPage /> },
              { path: 'usuarios', element: <UsersPage /> },
              { path: 'desenvolvedores', element: <DeveloperRequestsPage /> },
              { path: 'auditoria', element: <AuditPage /> },
            ],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]

export type AppRouter = ReturnType<typeof createBrowserRouter>

/** `memory` cria um roteador em memória (testes); sem ele, usa o histórico do navegador. */
export function createAppRouter(options: { memory?: string[] } = {}): AppRouter {
  return options.memory ? createMemoryRouter(routes, { initialEntries: options.memory }) : createBrowserRouter(routes)
}
