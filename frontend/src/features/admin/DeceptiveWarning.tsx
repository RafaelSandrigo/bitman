export function DeceptiveWarning() {
  return (
    <p role="alert" className="rounded-md border border-amber-700 bg-amber-950/50 px-3 py-2 text-sm text-amber-200">
      Atenção: o arquivo tem caracteres invisíveis ou bidirecionais, que podem esconder código. Eles aparecem marcados como ⟦U+…⟧.
    </p>
  )
}
