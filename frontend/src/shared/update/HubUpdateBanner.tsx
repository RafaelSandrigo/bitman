import { useQuery } from '@tanstack/react-query'
import { z } from 'zod'

/** O commit desta aba, gravado no bundle pelo build da imagem (`VITE_BITMAN_BUILD`). Vazio em desenvolvimento. */
function currentBuild(): string {
  return import.meta.env.VITE_BITMAN_BUILD ?? ''
}

const VersionSchema = z.object({ build: z.string() })

/** `version.json` sai do mesmo build (vite.config.ts) e o nginx o serve com `no-cache`. */
async function publishedBuild(): Promise<string | null> {
  const response = await fetch('/version.json', { cache: 'no-store', headers: { Accept: 'application/json' } })
  const parsed = VersionSchema.safeParse(response.ok ? await response.json() : null)
  return parsed.success ? parsed.data.build : null
}

/**
 * M10: uma aba aberta antes de um deploy continua com o JavaScript antigo. Ao focar a aba e a cada
 * 5 minutos, compara o build publicado com o desta aba e oferece recarregar. Nunca recarrega
 * sozinho: a pessoa pode estar no meio de uma partida.
 */
export function HubUpdateBanner() {
  const build = currentBuild()
  const published = useQuery({
    queryKey: ['hub-version'],
    queryFn: publishedBuild,
    enabled: build !== '',
    refetchInterval: 5 * 60_000,
    refetchOnWindowFocus: 'always',
    retry: false,
  })

  if (!published.data || published.data === build) return null
  return (
    <div role="status" className="border-b border-emerald-900 bg-emerald-950/70 px-4 py-2 text-center text-sm text-emerald-100">
      Há uma nova versão do Bitman.{' '}
      <button type="button" onClick={() => globalThis.location.reload()} className="font-semibold underline">
        Recarregar
      </button>
    </div>
  )
}
