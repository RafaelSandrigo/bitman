const numberFormat = new Intl.NumberFormat('pt-BR')
const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

export const formatScore = (value: number) => numberFormat.format(value)
export const formatDateTime = (value: string) => dateTimeFormat.format(new Date(value))
