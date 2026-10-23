import type { ReactNode, RefObject } from 'react'
import { isPlayUrl } from '../../shared/config'
import { Alert } from '../../shared/ui/Alert'
import type { PublicGame } from '../catalog/game'

/**
 * O jogo roda num iframe da origem isolada (ADR 0004):
 * - `sandbox="allow-scripts"` sem `allow-same-origin` → origem opaca, sem cookies nem storage (J1);
 * - sem `allow-top-navigation`, `allow-popups`, `allow-forms` e `allow-modals` (J3);
 * - nenhum recurso liberado via `allow` e sem Referer.
 * A borda e o aviso deixam claro onde começa o conteúdo de terceiros (J3). O `overlay` é do hub
 * (o resultado da partida) e fica por cima do iframe, nunca dentro dele.
 */
export function GameFrame({
  game,
  src,
  frameRef,
  overlay,
}: {
  game: PublicGame
  src: string
  frameRef?: RefObject<HTMLIFrameElement | null>
  overlay?: ReactNode
}) {
  return (
    <div className="rounded-lg border-2 border-dashed border-amber-700/70 p-2">
      {isPlayUrl(src) ? (
        <div className="relative">
          <iframe
            ref={frameRef}
            title={`Jogo: ${game.name}`}
            src={src}
            sandbox="allow-scripts"
            referrerPolicy="no-referrer"
            className="aspect-video w-full rounded bg-black"
          />
          {overlay}
        </div>
      ) : (
        <Alert tone="error">Não foi possível carregar o jogo com segurança.</Alert>
      )}
      <p className="mt-2 text-xs text-amber-200/80">
        Conteúdo enviado por {game.author_name}. Ele roda isolado: não acessa a sua conta, a internet nem outras páginas.
      </p>
    </div>
  )
}
