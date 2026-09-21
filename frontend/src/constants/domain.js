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

export const PROPOSAL_STATUSES = [
  { value: 'draft', label: 'Rascunho' },
  { value: 'sent', label: 'Enviada' },
  { value: 'accepted', label: 'Aceita' },
  { value: 'rejected', label: 'Recusada' },
]

export const PAYMENT_METHODS = [
  { value: 'pix', label: 'Pix' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'bank_transfer', label: 'Transferência bancária' },
  { value: 'credit_card', label: 'Cartão de crédito' },
  { value: 'cash', label: 'Dinheiro' },
]

export const PROPOSAL_ITEM_TYPES = [
  { value: 'recurring', label: 'Recorrente' },
  { value: 'one_time', label: 'Pontual' },
]

export const PROPOSAL_TEMPLATE_TYPES = [
  { value: 'observations', label: 'Observações' },
  { value: 'general_conditions', label: 'Condições gerais' },
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
  draft: 'bg-white text-gray-600 ring-1 ring-gray-200',
  sent: 'bg-blue-100 text-blue-700',
  accepted: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-600',
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

export function formatCurrency(cents) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format((Number(cents) || 0) / 100)
}

export function currencyToCents(value) {
  const normalized = String(value ?? '').replace(/\./g, '').replace(',', '.')
  const amount = Number.parseFloat(normalized)

  return Number.isFinite(amount) ? Math.round(amount * 100) : 0
}

export const DOCUMENT_MASK = ['###.###.###-##', '##.###.###/####-##']
export const PHONE_MASK = ['(##) ####-####', '(##) #####-####']

export const maskAttr = (mask) => JSON.stringify(mask)
