import { createBrowserRouter, createMemoryRouter, Navigate, type RouteObject } from 'react-router'
import { AdminLayout } from '../features/admin/AdminLayout'
import { AuditPage } from '../features/admin/pages/AuditPage'
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
import { HomePage } from '../features/catalog/pages/HomePage'
import { MyGamesPage } from '../features/submissions/pages/MyGamesPage'
import { SubmitGamePage } from '../features/submissions/pages/SubmitGamePage'
import { AdminGate, GuestOnly, RequirePermission } from './guards'
import { NotFoundPage } from './NotFoundPage'
import { PublicLayout } from './PublicLayout'

const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    children: [
      { index: true, element: <HomePage /> },
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
              { index: true, element: <Navigate to="submissoes" replace /> },
              { path: 'submissoes', element: <SubmissionsPage /> },
              { path: 'versoes', element: <VersionsPage /> },
              { path: 'importacoes', element: <Navigate to="/admin/versoes" replace /> },
              { path: 'versoes/:id', element: <VersionPage /> },
              { path: 'usuarios', element: <UsersPage /> },
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
