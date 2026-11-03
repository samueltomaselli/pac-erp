<script setup>
import { computed, ref, toRef } from 'vue'
import { X } from '@lucide/vue'
import { useOverlay } from '@/composables/useOverlay'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  size: { type: String, default: 'md', validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value) },
})

const emit = defineEmits(['close'])
const panel = ref(null)
const titleId = `modal-title-${Math.random().toString(36).slice(2, 9)}`
const widthClass = computed(
  () => ({ sm: 'sm:max-w-sm', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' })[props.size],
)

useOverlay(toRef(props, 'open'), panel, () => emit('close'))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[60] bg-gray-900/40" @click="emit('close')" />
    <div v-if="open" class="pointer-events-none fixed inset-0 z-[61] flex items-center justify-center p-4">
      <section
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        class="pointer-events-auto flex max-h-[92vh] w-full flex-col overflow-hidden rounded-xl bg-white shadow-xl"
        :class="widthClass"
      >
        <header class="flex shrink-0 items-start gap-3 border-b border-gray-200 px-5 py-4">
          <div class="min-w-0 flex-1">
            <h2 :id="titleId" class="font-display text-base font-semibold text-gray-900">{{ title }}</h2>
            <p v-if="description" class="mt-0.5 text-[13px] text-gray-500">{{ description }}</p>
          </div>
          <button type="button" class="size-8 rounded-md text-gray-400 hover:bg-gray-100" @click="emit('close')">
            <X class="mx-auto size-4" />
          </button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5"><slot /></div>
        <footer v-if="$slots.footer" class="flex shrink-0 justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
          <slot name="footer" />
        </footer>
      </section>
    </div>
  </Teleport>
</template>
