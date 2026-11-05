<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

defineProps({
  invalid: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const canvas = ref(null)
const hasStroke = ref(false)
let context = null
let drawing = false
let resizeObserver = null

function setup() {
  const element = canvas.value
  const ratio = window.devicePixelRatio || 1
  const { width, height } = element.getBoundingClientRect()

  element.width = Math.round(width * ratio)
  element.height = Math.round(height * ratio)
  context = element.getContext('2d')
  context.scale(ratio, ratio)
  context.lineWidth = 2.2
  context.lineCap = 'round'
  context.lineJoin = 'round'
  context.strokeStyle = '#111827'
}

function point(event) {
  const rect = canvas.value.getBoundingClientRect()
  return { x: event.clientX - rect.left, y: event.clientY - rect.top }
}

function start(event) {
  event.preventDefault()
  canvas.value.setPointerCapture(event.pointerId)
  drawing = true
  const { x, y } = point(event)
  context.beginPath()
  context.moveTo(x, y)
  context.lineTo(x + 0.1, y + 0.1)
  context.stroke()
}

function move(event) {
  if (!drawing) return
  const { x, y } = point(event)
  context.lineTo(x, y)
  context.stroke()
}

function end() {
  if (!drawing) return
  drawing = false
  hasStroke.value = true
  emit('update:modelValue', canvas.value.toDataURL('image/png'))
}

function clear() {
  const element = canvas.value
  context.save()
  context.setTransform(1, 0, 0, 1, 0, 0)
  context.clearRect(0, 0, element.width, element.height)
  context.restore()
  hasStroke.value = false
  emit('update:modelValue', '')
}

onMounted(() => {
  setup()
  resizeObserver = new ResizeObserver(() => {
    if (!hasStroke.value) setup()
  })
  resizeObserver.observe(canvas.value)
})

onBeforeUnmount(() => resizeObserver?.disconnect())
</script>

<template>
  <div>
    <div class="mb-1.5 flex items-center justify-between">
      <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assinatura digital</span>
      <button
        type="button"
        class="text-xs text-gray-600 underline underline-offset-2 hover:text-gray-900 disabled:opacity-40"
        :disabled="!hasStroke"
        @click="clear"
      >
        Limpar
      </button>
    </div>
    <div class="relative">
      <canvas
        ref="canvas"
        class="block h-44 w-full touch-none rounded-lg border bg-white"
        :class="invalid ? 'border-red-400' : 'border-gray-300'"
        aria-label="Quadro de assinatura: desenhe sua assinatura com o mouse ou o dedo"
        role="img"
        @pointerdown="start"
        @pointermove="move"
        @pointerup="end"
        @pointercancel="end"
      />
      <p
        v-if="!hasStroke"
        class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-gray-400"
      >
        Assine aqui com o mouse ou o dedo
      </p>
    </div>
  </div>
</template>
