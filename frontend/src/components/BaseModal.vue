<script setup>
import { computed, ref, toRef } from 'vue'
import { X } from '@lucide/vue'
import { useOverlay } from '@/composables/useOverlay'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v) },
  persistent: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const panel = ref(null)
const titleId = `modal-title-${Math.random().toString(36).slice(2, 9)}`

function close() {
  if (!props.persistent) {
    emit('close')
  }
}

useOverlay(toRef(props, 'open'), panel, close)

const widthClass = computed(
  () => ({ sm: 'sm:max-w-sm', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' })[props.size],
)
</script>
<template>
  <Teleport to="body">
    <Transition
      enter-active-class="duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-[60] bg-gray-900/40 transition-opacity" @click="close" />
    </Transition>
    <Transition
      enter-active-class="duration-200 ease-out"
      enter-from-class="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
      leave-active-class="duration-150 ease-in"
      leave-to-class="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
    >
      <div
        v-if="open"
        class="pointer-events-none fixed inset-0 z-[61] flex items-end justify-center sm:items-center sm:p-4"
      >
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
          class="pointer-events-auto flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-xl bg-white shadow-xl ring-1 ring-gray-900/5 transition-all focus:outline-none sm:rounded-xl"
          :class="widthClass"
        >
          <header class="flex shrink-0 items-start gap-3 border-b border-gray-200 px-5 py-4">
            <div class="min-w-0 flex-1">
              <h2 :id="titleId" class="font-display text-base font-semibold text-gray-900">
                {{ title }}
              </h2>
              <p v-if="description" class="mt-0.5 text-[13px] text-gray-500">{{ description }}</p>
            </div>
            <button
              type="button"
              class="-mr-1.5 -mt-1 inline-flex size-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/40"
              @click="emit('close')"
            >
              <span class="sr-only">Fechar</span>
              <X class="size-4" />
            </button>
          </header>
          <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
            <slot />
          </div>
          <footer
            v-if="$slots.footer"
            class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-end"
          >
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
