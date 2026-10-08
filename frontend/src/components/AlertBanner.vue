<script setup>
import { computed } from 'vue'
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'

const props = defineProps({
  tone: {
    type: String,
    default: 'error',
    validator: (v) => ['error', 'warning', 'success', 'info'].includes(v),
  },
  dismissible: { type: Boolean, default: false },
})

defineEmits(['dismiss'])

const styles = {
  error: { box: 'bg-red-50 text-red-700 ring-red-200', icon: CircleAlert },
  warning: { box: 'bg-amber-50 text-amber-800 ring-amber-200', icon: TriangleAlert },
  success: { box: 'bg-green-50 text-green-800 ring-green-200', icon: CircleCheck },
  info: { box: 'bg-sky-50 text-sky-800 ring-sky-200', icon: Info },
}

const current = computed(() => styles[props.tone])
</script>
<template>
  <div
    :role="tone === 'error' ? 'alert' : 'status'"
    class="flex items-start gap-2.5 rounded-lg px-4 py-3 text-sm ring-1"
    :class="current.box"
  >
    <component :is="current.icon" class="mt-0.5 size-4 shrink-0" />
    <div class="min-w-0 flex-1">
      <slot />
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="-m-1 rounded p-1 opacity-70 hover:opacity-100"
      @click="$emit('dismiss')"
    >
      <span class="sr-only">Fechar aviso</span>
      <X class="size-4" />
    </button>
  </div>
</template>
