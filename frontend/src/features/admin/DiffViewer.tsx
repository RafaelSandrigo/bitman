import { DeceptiveWarning } from './DeceptiveWarning'
import { hasDeceptiveChar, markDeceptive } from './deceptive'
import { lineDiff, type DiffLine } from './lineDiff'

const lineStyles: Record<DiffLine['kind'], string> = {
  same: 'text-slate-300',
  added: 'bg-emerald-950/70 text-emerald-200',
  removed: 'bg-red-950/70 text-red-200',
}
const markers: Record<DiffLine['kind'], string> = { same: ' ', added: '+', removed: '−' }

/**
 * M7: o que mudou desde a versão publicada, linha a linha. Como no FileViewer (J5), tudo é texto:
 * o React escapa cada linha e os caracteres enganosos aparecem marcados.
 */
export function DiffViewer({ path, before, after, previousNumber }: { path: string; before: string; after: string; previousNumber: number }) {
  const diff = lineDiff(before, after)
  if (diff === null) return <p className="text-slate-400">O diff é grande demais para mostrar aqui. Veja o arquivo completo.</p>

  const changed = diff.filter((line) => line.kind !== 'same').length
  return (
    <div className="space-y-2">
      {(hasDeceptiveChar(before) || hasDeceptiveChar(after)) && <DeceptiveWarning />}
      <p className="text-xs text-slate-400">
        {changed} {changed === 1 ? 'linha mudou' : 'linhas mudaram'} desde a versão {previousNumber} (no ar).
      </p>
      <pre aria-label={`Mudanças em ${path}`} className="max-h-[32rem] overflow-auto rounded-md border border-slate-800 bg-slate-950 p-3 font-mono text-xs leading-5">
        <code>
          {diff.map((line, index) => (
            <span key={index} className={`block whitespace-pre ${lineStyles[line.kind]}`} data-kind={line.kind}>
              <span aria-hidden="true" className="mr-2 inline-block w-8 select-none text-right text-slate-600">
                {line.oldNumber ?? ''}
              </span>
              <span aria-hidden="true" className="mr-2 inline-block w-8 select-none text-right text-slate-600">
                {line.newNumber ?? ''}
              </span>
              <span aria-hidden="true" className="mr-2 select-none">
                {markers[line.kind]}
              </span>
              {markDeceptive(line.text)}
            </span>
          ))}
        </code>
      </pre>
    </div>
  )
}
