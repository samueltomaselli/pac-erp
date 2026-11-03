import { nextTick, onBeforeUnmount, watch } from 'vue'

const stack = []

function onKeydown(event) {
  if (event.key !== 'Escape' || !stack.length) return
  event.stopPropagation()
  stack[stack.length - 1].close()
}

export function useOverlay(isOpen, panelRef, close) {
  const entry = { close }
  let previousFocus = null

  function activate() {
    previousFocus = document.activeElement
    stack.push(entry)
    if (stack.length === 1) document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
    nextTick(() => panelRef.value?.focus({ preventScroll: true }))
  }

  function deactivate() {
    const index = stack.indexOf(entry)
    if (index === -1) return
    stack.splice(index, 1)
    if (!stack.length) {
      document.removeEventListener('keydown', onKeydown)
      document.body.style.overflow = ''
    }
    if (previousFocus && document.contains(previousFocus)) previousFocus.focus({ preventScroll: true })
  }

  watch(isOpen, (open) => (open ? activate() : deactivate()), { immediate: true })
  onBeforeUnmount(deactivate)
}
