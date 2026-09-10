export const CUSTOMER_SEGMENTS = [
  { value: 'corban', label: 'Corban' },
  { value: 'real_estate', label: 'Imobiliária' },
  { value: 'other', label: 'Outro' },
]

export const CUSTOMER_STATUSES = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

export const TASK_PRIORITIES = [
  { value: 'high', label: 'Alta' },
  { value: 'medium', label: 'Média' },
  { value: 'low', label: 'Baixa' },
]

export const TASK_STATUSES = [
  { value: 'pending', label: 'Pendente' },
  { value: 'completed', label: 'Concluída' },
]

export const PRIORITY_CLASSES = {
  high: 'bg-red-100 text-red-600',
  medium: 'bg-amber-100 text-yellow-700',
  low: 'bg-white text-gray-600 ring-1 ring-gray-200',
}

export const STATUS_CLASSES = {
  active: 'bg-green-100 text-green-700',
  inactive: 'bg-white text-gray-600 ring-1 ring-gray-200',
  pending: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
}

export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = value.slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

export function formatDateTime(value) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export const DOCUMENT_MASK = ['###.###.###-##', '##.###.###/####-##']
export const PHONE_MASK = ['(##) ####-####', '(##) #####-####']

export const maskAttr = (mask) => JSON.stringify(mask)
