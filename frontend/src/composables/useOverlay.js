import { nextTick, onBeforeUnmount, watch } from 'vue'

const stack = []

function onKeydown(event) {
  if (event.key !== 'Escape' || !stack.length) {
    return
  }

  event.stopPropagation()
  stack[stack.length - 1].close()
}

function lockScroll() {
  document.body.style.overflow = stack.length ? 'hidden' : ''
}

export function useOverlay(isOpen, panelRef, close) {
  const entry = { close }
  let previousFocus = null

  function focusFirst() {
    const panel = panelRef.value

    if (!panel) {
      return
    }

    const target =
      panel.querySelector('[data-autofocus]') ||
      panel.querySelector('input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled])') ||
      panel

    target.focus({ preventScroll: true })
  }

  function trapFocus(event) {
    if (event.key !== 'Tab' || stack[stack.length - 1] !== entry || !panelRef.value) {
      return
    }

    const focusable = [
      ...panelRef.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ]

    if (!focusable.length) {
      return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  function activate() {
    previousFocus = document.activeElement
    stack.push(entry)

    if (stack.length === 1) {
      document.addEventListener('keydown', onKeydown)
    }

    document.addEventListener('keydown', trapFocus)
    lockScroll()
    nextTick(focusFirst)
  }

  function deactivate() {
    const index = stack.indexOf(entry)

    if (index === -1) {
      return
    }

    stack.splice(index, 1)

    if (!stack.length) {
      document.removeEventListener('keydown', onKeydown)
    }

    document.removeEventListener('keydown', trapFocus)
    lockScroll()

    if (previousFocus && document.contains(previousFocus)) {
      previousFocus.focus({ preventScroll: true })
    }
  }

  watch(
    isOpen,
    (value) => {
      if (value) {
        activate()
      } else {
        deactivate()
      }
    },
    { immediate: true },
  )

  onBeforeUnmount(deactivate)
}
