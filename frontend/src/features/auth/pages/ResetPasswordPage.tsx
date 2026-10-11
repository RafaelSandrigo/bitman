import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link, useSearchParams } from 'react-router'
import { z } from 'zod'
import { errorMessage, fieldErrors } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { TextField } from '../../../shared/ui/TextField'
import { resetPassword } from '../api'
import { AuthCard } from '../AuthCard'

const schema = z
  .object({
    password: z.string().min(8, 'A senha precisa ter pelo menos 8 caracteres.'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não conferem.',
  })
type Form = z.infer<typeof schema>

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const token = params.get('token')
  const email = params.get('email')
  const { register, handleSubmit, formState } = useForm<Form>({ resolver: zodResolver(schema) })
  const mutation = useMutation({
    mutationFn: (data: Form) => resetPassword({ ...data, token: token ?? '', email: email ?? '' }),
  })
  const errors = fieldErrors(mutation.error)
  const loginLink = (
    <Link to="/entrar" className="text-emerald-400 hover:underline">
      Ir para o login
    </Link>
  )

  if (!token || !email) {
    return (
      <AuthCard title="Redefinir senha" footer={loginLink}>
        <Alert tone="error">Link de redefinição inválido. Peça um novo em “Esqueci a senha”.</Alert>
      </AuthCard>
    )
  }

  return (
    <AuthCard title="Redefinir senha" footer={loginLink}>
      {mutation.isSuccess ? (
        <Alert tone="success">Senha redefinida. Entre com a nova senha.</Alert>
      ) : (
        <>
          {mutation.isError && <Alert tone="error">{errors.email ?? errors.token ?? errorMessage(mutation.error)}</Alert>}
          <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
            <TextField
              label="Nova senha"
              type="password"
              autoComplete="new-password"
              error={formState.errors.password?.message ?? errors.password}
              {...register('password')}
            />
            <TextField
              label="Confirme a nova senha"
              type="password"
              autoComplete="new-password"
              error={formState.errors.password_confirmation?.message}
              {...register('password_confirmation')}
            />
            <Button type="submit" disabled={mutation.isPending} className="w-full">
              Redefinir senha
            </Button>
          </form>
        </>
      )}
    </AuthCard>
  )
}
