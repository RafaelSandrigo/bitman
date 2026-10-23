/**
 * Caracteres que enganam quem lê código: controles bidirecionais ("Trojan Source", CVE-2021-42574),
 * de largura zero e o BOM. Aparecem marcados com o código (⟦U+202E⟧) e geram um aviso.
 */
const deceptiveChars = '[\\u061C\\u200B-\\u200F\\u202A-\\u202E\\u2060-\\u2064\\u2066-\\u2069\\uFEFF]'
const anyDeceptive = new RegExp(deceptiveChars)
const everyDeceptive = new RegExp(deceptiveChars, 'g')

function codepoint(char: string): string {
  return `⟦U+${(char.codePointAt(0) ?? 0).toString(16).toUpperCase().padStart(4, '0')}⟧`
}

export function hasDeceptiveChar(text: string): boolean {
  return anyDeceptive.test(text)
}

/** Troca cada caractere enganoso pela marca visível ⟦U+…⟧. */
export function markDeceptive(line: string): string {
  return line.replace(everyDeceptive, codepoint)
}
