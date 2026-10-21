<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, LogOut } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import TicketConversation from '@/components/TicketConversation.vue'
import { formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { useAuthStore } from '@/stores/useAuthStore'
import { getCustomerTicket } from '@/utils/api/customerTickets'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ticket = ref(null)
const loading = ref(true)
const error = ref('')

async function loadTicket() {
  loading.value = true
  ticket.value = null
  error.value = ''
  try {
    ticket.value = await getCustomerTicket(route.params.id)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar o chamado.')
  } finally {
    loading.value = false
  }
}

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

watch(() => route.params.id, loadTicket, { immediate: true })
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="sticky top-0 z-30 flex h-16 items-center gap-x-3 border-b border-gray-200 bg-white px-4 lg:px-6">
      <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-[13px] font-semibold text-white">
        R
      </div>
      <span class="font-display text-[15px] font-semibold text-gray-900">Área do cliente</span>
      <div class="ml-auto flex items-center gap-x-2">
        <span class="hidden max-w-50 truncate text-xs text-gray-500 sm:block">{{ auth.user?.email }}</span>
        <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
          aria-label="Sair"
          @click="onLogout"
        >
          <LogOut class="size-4" />
        </button>
      </div>
    </header>
    <main class="mx-auto max-w-4xl space-y-4 px-4 py-6 lg:px-6">
      <BaseButton variant="ghost" size="sm" class="!shadow-none" :to="{ name: 'customer.home' }">
        <ArrowLeft class="size-4" />
        Meus chamados
      </BaseButton>
      <div v-if="loading" class="panel space-y-4 p-6">
        <div class="h-5 w-56 animate-pulse rounded bg-gray-200" />
        <div class="h-24 animate-pulse rounded bg-gray-100" />
      </div>
      <AlertBanner v-else-if="error">{{ error }}</AlertBanner>
      <div v-else-if="ticket" class="space-y-4">
        <section class="panel p-5 sm:p-6">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-[13px] font-medium text-gray-500">{{ ticket.reference }}</span>
            <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
          </div>
          <h1 class="mt-2 wrap-break-word text-xl font-semibold text-gray-900">{{ ticket.title }}</h1>
          <p class="mt-1 text-[13px] text-gray-500">
            {{ ticket.category_label }} · Aberto em {{ formatDateTime(ticket.opened_at) }}
          </p>
          <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-gray-700">{{ ticket.description }}</p>
        </section>
        <AlertBanner v-if="ticket.status === 'waiting_customer'" tone="warning">
          A equipe está aguardando uma resposta sua. Comente abaixo para enviar as informações solicitadas; o chamado
          voltará automaticamente para “Em atendimento”.
        </AlertBanner>
        <TicketConversation :ticket="ticket" customer-mode @updated="loadTicket" />
      </div>
    </main>
  </div>
</template>
