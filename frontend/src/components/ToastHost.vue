<script setup>
import { CircleAlert, CircleCheck, Info, X } from '@lucide/vue'
import { dismissToast, toasts } from '@/composables/useToast'

const icons = { success: CircleCheck, error: CircleAlert, info: Info }
const iconClasses = { success: 'text-green-600', error: 'text-red-600', info: 'text-sky-600' }
</script>
<template>
  <Teleport to="body">
    <div
      aria-live="polite"
      class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
    >
      <TransitionGroup
        enter-active-class="duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-lg bg-white px-4 py-3 text-sm text-gray-800 shadow-lg ring-1 ring-gray-900/10 transition-all"
        >
          <component :is="icons[toast.tone]" class="mt-0.5 size-4 shrink-0" :class="iconClasses[toast.tone]" />
          <p class="min-w-0 flex-1">{{ toast.message }}</p>
          <button
            type="button"
            class="-m-1 rounded p-1 text-gray-400 hover:text-gray-700"
            @click="dismissToast(toast.id)"
          >
            <span class="sr-only">Fechar</span>
            <X class="size-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
