<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  CheckSquare,
  FileText,
  LayoutDashboard,
  Package,
  TextQuote,
  Users,
} from '@lucide/vue'
import AppHeader from '@/components/AppHeader.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import { useAuthStore } from '@/stores/useAuthStore'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  back: { type: [String, Object], default: null },
  width: { type: String, default: 'full', validator: (v) => ['full', 'wide', 'narrow'].includes(v) },
})

const router = useRouter()
const auth = useAuthStore()

const sidebarOpen = ref(false)

const navigation = [
  {
    label: 'Operação',
    items: [
      { name: 'admin.home', label: 'Início', icon: LayoutDashboard },
      { name: 'admin.customers.index', label: 'Clientes', icon: Users, match: '/admin/clientes' },
      {
        name: 'admin.proposals.index',
        label: 'Propostas',
        icon: FileText,
        match: '/admin/propostas',
        exclude: ['/admin/propostas/catalogo', '/admin/propostas/modelos'],
      },
      { name: 'admin.tasks.index', label: 'Tarefas', icon: CheckSquare, match: '/admin/tarefas' },
    ],
  },
  {
    label: 'Cadastros',
    items: [
      {
        name: 'admin.proposals.catalog',
        label: 'Catálogo de itens',
        icon: Package,
        match: '/admin/propostas/catalogo',
      },
      {
        name: 'admin.proposals.templates',
        label: 'Modelos de texto',
        icon: TextQuote,
        match: '/admin/propostas/modelos',
      },
    ],
  },
]

const widthClasses = { full: '', wide: 'mx-auto max-w-6xl', narrow: 'mx-auto max-w-3xl' }

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
<template>
  <div class="flex min-h-screen bg-gray-50">
    <AppSidebar v-model:open="sidebarOpen" :navigation="navigation" :email="auth.user?.email" @logout="onLogout" />
    <div class="flex min-w-0 flex-1 flex-col">
      <AppHeader :title="title" :subtitle="subtitle" :back="back" @toggle="sidebarOpen = !sidebarOpen">
        <template #actions>
          <slot name="actions" />
        </template>
      </AppHeader>
      <main class="flex-1 px-3 py-5 sm:px-4 sm:py-6 lg:px-6">
        <div :class="widthClasses[width]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
