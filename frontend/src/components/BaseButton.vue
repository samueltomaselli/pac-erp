<script setup>
import { computed } from 'vue'
import { LoaderCircle } from '@lucide/vue'

const props = defineProps({
  type: { type: String, default: 'button' },
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'ghost-danger'].includes(v),
  },
  size: { type: String, default: 'md', validator: (s) => ['sm', 'md', 'lg'].includes(s) },
  to: { type: [String, Object], default: null },
  icon: { type: Boolean, default: false },
  label: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
})

const isDisabled = computed(() => props.disabled || props.loading)

const sizeClasses = computed(() => {
  if (props.icon) {
    return { sm: 'size-8', md: 'size-9', lg: 'size-10' }[props.size]
  }

  return {
    sm: 'h-8 gap-1.5 px-3 text-[13px]',
    md: 'h-9 gap-2 px-3.5 text-sm',
    lg: 'h-10 gap-2 px-4 text-sm',
  }[props.size]
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'border border-gray-300 bg-white text-gray-700 shadow-xs hover:bg-gray-50 hover:text-gray-900 active:bg-gray-100'
    case 'ghost':
      return 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900 active:bg-gray-200'
    case 'ghost-danger':
      return 'bg-transparent text-gray-500 hover:bg-red-50 hover:text-red-600 active:bg-red-100'
    case 'danger':
      return 'bg-red-600 text-white shadow-xs hover:bg-red-700 active:bg-red-800'
    case 'primary':
    default:
      return 'bg-theme-light-700 text-white shadow-xs hover:bg-theme-light-800 active:bg-theme-light-900'
  }
})

const classes = computed(() => [
  'inline-flex shrink-0 select-none items-center justify-center whitespace-nowrap rounded-md font-medium leading-none',
  'transition-colors duration-150 ease-out',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/60 focus-visible:ring-offset-1',
  sizeClasses.value,
  variantClasses.value,
  props.block && 'w-full',
  isDisabled.value && 'pointer-events-none opacity-50',
])

const iconSize = computed(() => (props.size === 'sm' ? 'size-3.5' : 'size-4'))
</script>
<template>
  <router-link
    v-if="to && !isDisabled"
    :to="to"
    :class="classes"
    :aria-label="label || undefined"
    :title="icon ? label || undefined : undefined"
  >
    <slot />
  </router-link>
  <button
    v-else
    :type="type"
    :disabled="isDisabled"
    :aria-busy="loading || undefined"
    :aria-label="label || undefined"
    :title="icon ? label || undefined : undefined"
    :class="classes"
  >
    <LoaderCircle v-if="loading" :class="[iconSize, 'animate-spin']" />
    <slot v-if="!(loading && icon)" />
  </button>
</template>
