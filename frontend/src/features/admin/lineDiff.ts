/** M7: diff linha a linha para o Portão 2. Só texto: as linhas nunca viram HTML (J5). */
export type DiffLine = { kind: 'same' | 'added' | 'removed'; text: string; oldNumber: number | null; newNumber: number | null }

/** Acima disso (linhas × linhas, depois de cortar o começo e o fim iguais), o diff não é calculado. */
export const MAX_DIFF_CELLS = 4_000_000

function commonPrefix(a: string[], b: string[]): number {
  let n = 0
  while (n < a.length && n < b.length && a[n] === b[n]) n++
  return n
}

function commonSuffix(a: string[], b: string[], prefix: number): number {
  let n = 0
  while (n < a.length - prefix && n < b.length - prefix && a[a.length - 1 - n] === b[b.length - 1 - n]) n++
  return n
}

/** Tabela de LCS (maior subsequência comum) do miolo que difere, de trás para a frente. */
function lcsTable(a: string[], b: string[]): Uint32Array[] {
  const table = Array.from({ length: a.length + 1 }, () => new Uint32Array(b.length + 1))
  for (let i = a.length - 1; i >= 0; i--) {
    for (let j = b.length - 1; j >= 0; j--) {
      table[i]![j] = a[i] === b[j] ? table[i + 1]![j + 1]! + 1 : Math.max(table[i + 1]![j]!, table[i]![j + 1]!)
    }
  }
  return table
}

function walkMiddle(a: string[], b: string[]): Omit<DiffLine, 'oldNumber' | 'newNumber'>[] {
  const table = lcsTable(a, b)
  const out: Omit<DiffLine, 'oldNumber' | 'newNumber'>[] = []
  let i = 0
  let j = 0
  while (i < a.length || j < b.length) {
    if (i < a.length && j < b.length && a[i] === b[j]) {
      out.push({ kind: 'same', text: a[i++]! })
      j++
    } else if (j < b.length && (i === a.length || table[i]![j + 1]! > table[i + 1]![j]!)) {
      out.push({ kind: 'added', text: b[j++]! })
    } else {
      out.push({ kind: 'removed', text: a[i++]! })
    }
  }
  return out
}

function numbered(lines: Omit<DiffLine, 'oldNumber' | 'newNumber'>[]): DiffLine[] {
  let oldNumber = 0
  let newNumber = 0
  return lines.map((line) => ({
    ...line,
    oldNumber: line.kind === 'added' ? null : ++oldNumber,
    newNumber: line.kind === 'removed' ? null : ++newNumber,
  }))
}

/**
 * Diff de `before` para `after`, ou `null` se for grande demais para calcular no navegador
 * (o admin continua vendo o arquivo completo).
 */
export function lineDiff(before: string, after: string): DiffLine[] | null {
  const a = before.split('\n')
  const b = after.split('\n')
  const prefix = commonPrefix(a, b)
  const suffix = commonSuffix(a, b, prefix)
  const middleA = a.slice(prefix, a.length - suffix)
  const middleB = b.slice(prefix, b.length - suffix)
  if ((middleA.length + 1) * (middleB.length + 1) > MAX_DIFF_CELLS) return null

  const same = (text: string) => ({ kind: 'same' as const, text })
  return numbered([...a.slice(0, prefix).map(same), ...walkMiddle(middleA, middleB), ...a.slice(a.length - suffix).map(same)])
}
