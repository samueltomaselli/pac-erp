<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AlertBanner from '@/components/AlertBanner.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import AppLayout from '@/components/AppLayout.vue'
import TicketConversation from '@/components/TicketConversation.vue'
import { formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { getCustomerTicket } from '@/utils/api/customerTickets'

const route = useRoute()
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

watch(() => route.params.id, loadTicket, { immediate: true })
</script>

<template>
  <AppLayout
    :title="ticket ? ticket.reference : 'Chamado'"
    :subtitle="ticket?.title ?? ''"
    :back="{ name: 'customer.tickets.index' }"
    width="wide"
  >
    <div class="space-y-4">
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
            {{ ticket.category_label }}
          </p>
          <dl class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div>
              <dt class="stat-label">Aberto em</dt>
              <dd class="mt-0.5 text-[13px] text-gray-700">{{ formatDateTime(ticket.opened_at) }}</dd>
            </div>
            <div>
              <dt class="stat-label">Última atualização</dt>
              <dd class="mt-0.5 text-[13px] text-gray-700">{{ formatDateTime(ticket.updated_at) }}</dd>
            </div>
            <div v-if="ticket.resolved_at">
              <dt class="stat-label">Resolvido em</dt>
              <dd class="mt-0.5 text-[13px] text-gray-700">{{ formatDateTime(ticket.resolved_at) }}</dd>
            </div>
            <div v-if="ticket.closed_at">
              <dt class="stat-label">Fechado em</dt>
              <dd class="mt-0.5 text-[13px] text-gray-700">{{ formatDateTime(ticket.closed_at) }}</dd>
            </div>
          </dl>
          <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-gray-700">{{ ticket.description }}</p>
        </section>
        <AlertBanner v-if="ticket.status === 'waiting_customer'" tone="warning">
          A equipe está aguardando uma resposta sua. Comente abaixo para enviar as informações solicitadas; o chamado
          voltará automaticamente para “Em atendimento”.
        </AlertBanner>
        <TicketConversation :ticket="ticket" customer-mode @updated="loadTicket" />
      </div>
    </div>
  </AppLayout>
</template>
