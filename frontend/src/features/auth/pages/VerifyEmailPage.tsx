import { useMutation, useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { Link, useLocation, useSearchParams } from 'react-router'
import { ApiError, errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { isVerificationLink, resendVerification, verifyEmail } from '../api'
import { AuthCard } from '../AuthCard'
import { useCurrentUser, useRefreshSession } from '../session'

/**
 * Duas situações: sem `link`, orienta e permite reenviar o e-mail; com `link` (vindo do e-mail),
 * conclui a verificação pela API, o que exige estar logado com a mesma conta.
 */
export function VerifyEmailPage() {
  const [params] = useSearchParams()
  const location = useLocation()
  const link = params.get('link')
  const { data: user, isPending } = useCurrentUser()
  const refreshSession = useRefreshSession()

  const verification = useQuery({
    queryKey: ['verify-email', link],
    queryFn: () => verifyEmail(link ?? ''),
    enabled: Boolean(link && user && !user.email_verified && isVerificationLink(link)),
    retry: false,
    staleTime: Infinity,
  })
  const resend = useMutation({ mutationFn: resendVerification })

  const verified = verification.isSuccess
  useEffect(() => {
    if (verified) void refreshSession()
  }, [verified, refreshSession])

  if (isPending) return <AuthCard title="Confirmar e-mail">Carregando…</AuthCard>

  if (!user) {
    const next = encodeURIComponent(location.pathname + location.search)
    return (
      <AuthCard title="Confirmar e-mail">
        <Alert tone="info">
          <Link to={`/entrar?next=${next}`} className="text-emerald-400 hover:underline">
            Entre na sua conta
          </Link>{' '}
          para confirmar o e-mail.
        </Alert>
      </AuthCard>
    )
  }

  if (user.email_verified || verified) {
    return (
      <AuthCard title="Confirmar e-mail">
        <Alert tone="success">E-mail confirmado. Bom jogo!</Alert>
        <Link to="/" className="text-emerald-400 hover:underline">
          Ir para o início
        </Link>
      </AuthCard>
    )
  }

  // 403 = assinatura inválida/expirada ou link de outra conta.
  const invalidLink =
    (link !== null && !isVerificationLink(link)) || (verification.error instanceof ApiError && verification.error.status === 403)

  return (
    <AuthCard title="Confirmar e-mail">
      {verification.isLoading && <p>Confirmando…</p>}
      {invalidLink ? (
        <Alert tone="error">Link de confirmação inválido ou expirado. Peça um novo abaixo.</Alert>
      ) : (
        verification.isError && <Alert tone="error">{errorMessage(verification.error)}</Alert>
      )}
      {!link && (
        <p className="text-slate-300">
          Enviamos um link de confirmação para <strong>{user.email}</strong>. Confirme o e-mail para jogar e aparecer nos rankings.
        </p>
      )}
      {resend.isSuccess && <Alert tone="success">Enviamos um novo link. Confira sua caixa de entrada.</Alert>}
      {resend.isError && <Alert tone="error">{errorMessage(resend.error)}</Alert>}
      {!verification.isLoading && (
        <Button variant="secondary" disabled={resend.isPending} onClick={() => resend.mutate()}>
          Reenviar e-mail de confirmação
        </Button>
      )}
    </AuthCard>
  )
}
