import { DeceptiveWarning } from './DeceptiveWarning'
import { hasDeceptiveChar, markDeceptive } from './deceptive'
import type { FileContent } from './versions'

/**
 * J5: o código revisado é sempre texto. O React escapa cada linha; nada vira HTML, iframe ou
 * script. Sem realce de sintaxe por enquanto (docs/melhorias.md).
 */
export function FileViewer({ file }: { file: FileContent }) {
  const hasDeceptive = hasDeceptiveChar(file.content)
  const lines = file.content.split('\n')

  return (
    <div className="space-y-2">
      {hasDeceptive && <DeceptiveWarning />}
      <pre
        aria-label={`Conteúdo de ${file.path}`}
        className="max-h-[32rem] overflow-auto rounded-md border border-slate-800 bg-slate-950 p-3 font-mono text-xs leading-5 text-slate-200"
      >
        <code>
          {lines.map((line, index) => (
            <span key={index} className="block whitespace-pre">
              <span aria-hidden="true" className="mr-4 inline-block w-10 select-none text-right text-slate-600">
                {index + 1}
              </span>
              {markDeceptive(line)}
            </span>
          ))}
        </code>
      </pre>
    </div>
  )
}
