import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router'
import { z } from 'zod'
import { errorMessage, fieldErrors } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { TextArea } from '../../../shared/ui/TextArea'
import { TextField } from '../../../shared/ui/TextField'
import { submitGame } from '../api'

const THUMBNAIL_TYPES = ['image/png', 'image/jpeg', 'image/webp']
const THUMBNAIL_MAX_BYTES = 2 * 1024 * 1024

/** Espelha as regras do backend para dar retorno rápido; quem decide é sempre o servidor (G1–G3, A4). */
const schema = z.object({
  name: z.string().trim().min(2, 'Informe o nome do jogo.').max(60, 'Use no máximo 60 caracteres.'),
  description: z.string().trim().min(1, 'Descreva o jogo.').max(2000, 'Use no máximo 2000 caracteres.'),
  author_name: z.string().trim().min(2, 'Informe o autor.').max(60, 'Use no máximo 60 caracteres.'),
  repository_url: z
    .string()
    .trim()
    .regex(/^https:\/\/(github\.com|gitlab\.com|codeberg\.org)\//i, 'Use o endereço HTTPS do repositório no GitHub, GitLab ou Codeberg.'),
  ref: z.string().trim().min(1, 'Informe a branch, tag ou commit.').max(100, 'Use no máximo 100 caracteres.'),
  thumbnail: z
    .custom<FileList>((value) => value instanceof FileList && value.length === 1, 'Escolha uma imagem.')
    .refine((files) => THUMBNAIL_TYPES.includes(files[0]?.type ?? ''), 'A imagem deve ser PNG, JPEG ou WebP.')
    .refine((files) => (files[0]?.size ?? 0) <= THUMBNAIL_MAX_BYTES, 'A imagem deve ter no máximo 2 MB.'),
})
type Form = z.infer<typeof schema>
const fields = ['name', 'description', 'author_name', 'repository_url', 'ref', 'thumbnail'] as const

export function SubmitGamePage() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { register, handleSubmit, setError, formState } = useForm<Form>({
    resolver: zodResolver(schema),
    defaultValues: { ref: 'main' },
  })

  const mutation = useMutation({
    mutationFn: (data: Form) => submitGame({ ...data, thumbnail: data.thumbnail[0] as File }),
    onSuccess: async (game) => {
      await queryClient.invalidateQueries({ queryKey: ['my-games'] })
      await navigate('/desenvolvedor/jogos', { state: { submitted: game.name } })
    },
    onError: (error) => {
      for (const [field, message] of Object.entries(fieldErrors(error))) {
        const known = fields.find((f) => f === field)
        if (known) setError(known, { message })
      }
    },
  })
  const hasFieldErrors = Object.keys(fieldErrors(mutation.error)).length > 0

  return (
    <section className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <h1 className="text-2xl font-bold">Enviar jogo</h1>
        <p className="text-sm text-slate-400">
          O repositório precisa ser público e ter <code>index.html</code>, <code>style.css</code> e <code>game.js</code> na
          raiz. Nada é baixado agora: um admin autoriza a importação, revisa o commit e cada arquivo antes de publicar.
        </p>
      </div>

      {mutation.isError && !hasFieldErrors && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}

      <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
        <TextField label="Nome do jogo" maxLength={60} error={formState.errors.name?.message} {...register('name')} />
        <TextArea label="Descrição" maxLength={2000} error={formState.errors.description?.message} {...register('description')} />
        <TextField label="Autor" maxLength={60} error={formState.errors.author_name?.message} {...register('author_name')} />
        <TextField
          label="Repositório Git"
          type="url"
          placeholder="https://github.com/usuario/meu-jogo"
          error={formState.errors.repository_url?.message}
          {...register('repository_url')}
        />
        <TextField label="Branch, tag ou commit" maxLength={100} error={formState.errors.ref?.message} {...register('ref')} />
        <TextField
          label="Thumbnail (PNG, JPEG ou WebP, até 2 MB)"
          type="file"
          accept={THUMBNAIL_TYPES.join(',')}
          error={formState.errors.thumbnail?.message}
          {...register('thumbnail')}
        />
        <Button type="submit" disabled={mutation.isPending} className="w-full">
          {mutation.isPending ? 'Enviando…' : 'Enviar para análise'}
        </Button>
      </form>
    </section>
  )
}
