<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: { type: String, default: 'submit' },
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger'].includes(v),
  },
  size: { type: String, default: 'md', validator: (s) => ['sm', 'md'].includes(s) },
  disabled: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
})

const sizeClasses = computed(() =>
  props.size === 'sm' ? 'h-7 gap-1.5 px-2.5 text-[12px]' : 'h-8 gap-2 px-3 text-[13px]',
)

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:text-gray-900 active:bg-gray-100'
    case 'ghost':
      return 'bg-transparent text-gray-700 hover:bg-gray-100 active:bg-gray-200'
    case 'danger':
      return 'bg-red-600 text-white shadow-sm hover:bg-red-700 active:bg-red-800'
    case 'primary':
    default:
      return 'bg-theme-light-700 text-white shadow-sm hover:bg-theme-light-900 active:bg-theme-light-900'
  }
})
</script>
<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="[
      'inline-flex select-none items-center justify-center whitespace-nowrap rounded-md font-medium leading-none tracking-normal',
      'transition-colors duration-150 ease-out',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/60 focus-visible:ring-offset-2',
      'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
      sizeClasses,
      variantClasses,
      block && 'w-full',
    ]"
  >
    <slot />
  </button>
</template>