export function formatSize(bytes: number | null): string {
  if (bytes === null) return 'acima do limite'
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KiB`
}
