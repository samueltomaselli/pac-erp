import { reactive } from 'vue'

export const toasts = reactive([])

let nextId = 1

export function dismissToast(id) {
  const index = toasts.findIndex((toast) => toast.id === id)

  if (index !== -1) {
    toasts.splice(index, 1)
  }
}

function push(tone, message, duration = 3500) {
  const id = nextId++

  toasts.push({ id, tone, message })

  if (toasts.length > 4) {
    toasts.shift()
  }

  setTimeout(() => dismissToast(id), duration)
}

export function errorMessage(error, fallback) {
  return error?.response?.data?.message || fallback
}

export function useToast() {
  return {
    success: (message) => push('success', message),
    error: (message) => push('error', message, 5000),
    info: (message) => push('info', message),
  }
}
