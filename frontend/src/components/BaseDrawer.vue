<script setup>
import { computed, ref, toRef } from 'vue'
import { X } from '@lucide/vue'
import { useOverlay } from '@/composables/useOverlay'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  size: { type: String, default: 'md', validator: (v) => ['md', 'lg'].includes(v) },
})

const emit = defineEmits(['close'])

const panel = ref(null)
const titleId = `drawer-title-${Math.random().toString(36).slice(2, 9)}`

function close() {
  emit('close')
}

useOverlay(toRef(props, 'open'), panel, close)

const widthClass = computed(() => (props.size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-lg'))
</script>
<template>
  <Teleport to="body">
    <Transition
      enter-active-class="duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="duration-200 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-[60] bg-gray-900/30 transition-opacity" @click="close" />
    </Transition>
    <Transition
      enter-active-class="duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]"
      enter-from-class="translate-x-full"
      leave-active-class="duration-200 ease-in"
      leave-to-class="translate-x-full"
    >
      <aside
        v-if="open"
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        class="fixed inset-y-0 right-0 z-[61] flex w-full flex-col bg-white shadow-2xl ring-1 ring-gray-900/5 transition-transform focus:outline-none"
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
            @click="close"
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
          class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3"
        >
          <slot name="footer" />
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>
