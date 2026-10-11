import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { TextField } from '../../../shared/ui/TextField'
import { requestPasswordReset } from '../api'
import { AuthCard } from '../AuthCard'

const schema = z.object({ email: z.email('Informe um e-mail válido.') })
type Form = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const { register, handleSubmit, formState } = useForm<Form>({ resolver: zodResolver(schema) })
  const mutation = useMutation({ mutationFn: (data: Form) => requestPasswordReset(data.email) })

  return (
    <AuthCard
      title="Esqueci a senha"
      footer={
        <Link to="/entrar" className="text-emerald-400 hover:underline">
          Voltar para o login
        </Link>
      }
    >
      {mutation.isSuccess ? (
        <Alert tone="success">{mutation.data}</Alert>
      ) : (
        <>
          {mutation.isError && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
          <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
            <TextField label="E-mail" type="email" autoComplete="email" error={formState.errors.email?.message} {...register('email')} />
            <Button type="submit" disabled={mutation.isPending} className="w-full">
              Enviar link
            </Button>
          </form>
        </>
      )}
    </AuthCard>
  )
}
