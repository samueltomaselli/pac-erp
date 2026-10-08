<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { LogOut, PanelLeftClose, PanelLeftOpen, X } from '@lucide/vue'

const props = defineProps({
  navigation: { type: Array, required: true },
  open: { type: Boolean, default: false },
  email: { type: String, default: '' },
})

const emit = defineEmits(['update:open', 'logout'])

const route = useRoute()

const STORAGE_KEY = 'erp.sidebar.expanded'

const storedExpanded = ref(readStored())

function readStored() {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'false'
  } catch {
    return true
  }
}

watch(storedExpanded, (value) => {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {}
})

const expanded = computed(() => storedExpanded.value || props.open)

const width = computed(() => (expanded.value ? 240 : 64))

function isActive(item) {
  if (!item.match) {
    return route.name === item.name
  }

  if (item.exclude?.some((path) => route.path.startsWith(path))) {
    return false
  }

  return route.path.startsWith(item.match)
}

function closeOnMobile() {
  emit('update:open', false)
}

watch(
  () => route.fullPath,
  () => closeOnMobile(),
)
</script>
<template>
  <Transition
    enter-active-class="duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="open" class="fixed inset-0 z-40 bg-gray-900/40 transition-opacity lg:hidden" @click="closeOnMobile" />
  </Transition>
  <aside
    class="fixed inset-y-0 left-0 z-50 flex shrink-0 flex-col border-r border-gray-200 bg-white transition-[width,transform] duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="open ? 'translate-x-0 shadow-xl lg:shadow-none' : '-translate-x-full'"
    :style="{ width: `${width}px` }"
  >
    <div class="flex h-16 shrink-0 items-center gap-x-2.5 border-b border-gray-200 px-4">
      <div
        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-sm font-semibold text-white"
      >
        R
      </div>
      <span v-if="expanded" class="min-w-0 flex-1 truncate font-display text-base font-semibold text-gray-900">
        Rauzee
      </span>
      <button
        v-if="expanded"
        type="button"
        class="hidden size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35 lg:flex"
        title="Recolher menu"
        @click="storedExpanded = false"
      >
        <span class="sr-only">Recolher menu lateral</span>
        <PanelLeftClose class="size-4" />
      </button>
      <button
        type="button"
        class="flex size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 lg:hidden"
        @click="closeOnMobile"
      >
        <span class="sr-only">Fechar menu lateral</span>
        <X class="size-4" />
      </button>
    </div>
    <button
      v-if="!expanded"
      type="button"
      class="mx-auto mt-3 hidden size-9 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35 lg:flex"
      title="Expandir menu"
      @click="storedExpanded = true"
    >
      <span class="sr-only">Expandir menu lateral</span>
      <PanelLeftOpen class="size-4" />
    </button>
    <nav class="flex-1 space-y-4 overflow-y-auto overflow-x-hidden px-3 py-3">
      <div v-for="group in navigation" :key="group.label">
        <p v-if="expanded" class="px-2.5 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
          {{ group.label }}
        </p>
        <div v-else class="mx-auto mb-2 h-px w-6 bg-gray-200" />
        <ul class="space-y-0.5">
          <li v-for="item in group.items" :key="item.name">
            <router-link
              :to="{ name: item.name }"
              :title="expanded ? undefined : item.label"
              :aria-current="isActive(item) ? 'page' : undefined"
              class="group/item relative flex h-9 w-full items-center gap-x-2.5 rounded-md px-2.5 text-sm transition-colors duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35"
              :class="[
                isActive(item)
                  ? 'bg-theme-light-500/10 font-medium text-theme-light-800'
                  : 'font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900',
                !expanded && 'justify-center',
              ]"
            >
              <component
                :is="item.icon"
                class="size-[18px] shrink-0 transition-colors duration-150"
                :class="isActive(item) ? 'text-theme-light-700' : 'text-gray-500 group-hover/item:text-gray-800'"
              />
              <span v-if="expanded" class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>
    <div class="shrink-0 border-t border-gray-200 p-3">
      <div class="flex items-center gap-2" :class="!expanded && 'justify-center'">
        <div
          v-if="expanded"
          class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold uppercase text-gray-600"
        >
          {{ email?.slice(0, 1) || '?' }}
        </div>
        <span v-if="expanded" class="min-w-0 flex-1 truncate text-xs text-gray-600" :title="email">
          {{ email }}
        </span>
        <button
          type="button"
          class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30"
          title="Sair"
          @click="$emit('logout')"
        >
          <span class="sr-only">Sair</span>
          <LogOut class="size-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
