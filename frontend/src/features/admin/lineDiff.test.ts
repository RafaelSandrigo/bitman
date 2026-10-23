import { describe, expect, it } from 'vitest'
import { lineDiff, MAX_DIFF_CELLS } from './lineDiff'

/* M7: diff linha a linha do Portão 2. */
describe('lineDiff', () => {
  it('marca a linha trocada como removida e adicionada, com a numeração de cada lado', () => {
    expect(lineDiff('a\nb\nc', 'a\nB\nc')).toEqual([
      { kind: 'same', text: 'a', oldNumber: 1, newNumber: 1 },
      { kind: 'removed', text: 'b', oldNumber: 2, newNumber: null },
      { kind: 'added', text: 'B', oldNumber: null, newNumber: 2 },
      { kind: 'same', text: 'c', oldNumber: 3, newNumber: 3 },
    ])
  })

  it('inclusão e remoção no meio preservam as linhas iguais', () => {
    const diff = lineDiff('a\nb\nc\nd', 'a\nc\nd\ne')!
    expect(diff.map((line) => `${line.kind[0]}${line.text}`)).toEqual(['sa', 'rb', 'sc', 'sd', 'ae'])
  })

  it('arquivos iguais: só linhas iguais', () => {
    expect(lineDiff('x\ny', 'x\ny')!.every((line) => line.kind === 'same')).toBe(true)
  })

  it('grande demais: não calcula (o admin vê o arquivo completo)', () => {
    const lines = Math.ceil(Math.sqrt(MAX_DIFF_CELLS)) + 1
    const before = Array.from({ length: lines }, (_, i) => `a${i}`).join('\n')
    const after = Array.from({ length: lines }, (_, i) => `b${i}`).join('\n')

    expect(lineDiff(before, after)).toBeNull()
  })
})
