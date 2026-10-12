import type { FileContent } from './versions'

/**
 * Caracteres que enganam quem lê código: controles bidirecionais ("Trojan Source", CVE-2021-42574),
 * de largura zero e o BOM. Aparecem marcados com o código (⟦U+202E⟧) e geram um aviso.
 */
const deceptiveChars = '[\\u061C\\u200B-\\u200F\\u202A-\\u202E\\u2060-\\u2064\\u2066-\\u2069\\uFEFF]'
const hasDeceptiveChar = new RegExp(deceptiveChars)
const deceptive = new RegExp(deceptiveChars, 'g')

function codepoint(char: string): string {
  return `⟦U+${(char.codePointAt(0) ?? 0).toString(16).toUpperCase().padStart(4, '0')}⟧`
}

/**
 * J5: o código revisado é sempre texto. O React escapa cada linha; nada vira HTML, iframe ou
 * script. Sem realce de sintaxe por enquanto (docs/melhorias.md).
 */
export function FileViewer({ file }: { file: FileContent }) {
  const hasDeceptive = hasDeceptiveChar.test(file.content)
  const lines = file.content.split('\n')

  return (
    <div className="space-y-2">
      {hasDeceptive && (
        <p role="alert" className="rounded-md border border-amber-700 bg-amber-950/50 px-3 py-2 text-sm text-amber-200">
          Atenção: o arquivo tem caracteres invisíveis ou bidirecionais, que podem esconder código. Eles aparecem marcados como ⟦U+…⟧.
        </p>
      )}
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
              {line.replace(deceptive, codepoint)}
            </span>
          ))}
        </code>
      </pre>
    </div>
  )
}
