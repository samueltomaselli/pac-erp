import { reactive } from 'vue'

export const confirmState = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirmar',
  cancelLabel: 'Cancelar',
  tone: 'primary',
  resolve: null,
})

export function settleConfirm(value) {
  confirmState.open = false
  confirmState.resolve?.(value)
  confirmState.resolve = null
}

export function useConfirm() {
  return function confirm({
    title,
    message = '',
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    tone = 'primary',
  }) {
    if (confirmState.resolve) {
      confirmState.resolve(false)
    }

    Object.assign(confirmState, { title, message, confirmLabel, cancelLabel, tone, open: true })

    return new Promise((resolve) => {
      confirmState.resolve = resolve
    })
  }
}
