<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Plus } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import CustomerTicketFormDrawer from '@/components/drawers/CustomerTicketFormDrawer.vue'
import { formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { listCustomerTickets } from '@/utils/api/customerTickets'

const router = useRouter()

const tickets = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const error = ref('')
const drawerOpen = ref(false)

async function fetchTickets() {
  loading.value = true
  error.value = ''
  try {
    const response = await listCustomerTickets({ page: page.value })
    tickets.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar seus chamados.')
  } finally {
    loading.value = false
  }
}

function onSaved(ticket) {
  if (ticket?.id) {
    router.push({ name: 'customer.tickets.show', params: { id: ticket.id } })
    return
  }
  if (page.value !== 1) {
    page.value = 1
    return
  }
  fetchTickets()
}

watch(page, fetchTickets)
onMounted(fetchTickets)
</script>
<template>
  <AppLayout title="Chamados" subtitle="Acompanhe suas solicitações e fale com a nossa equipe">
    <template #actions>
      <BaseButton @click="drawerOpen = true">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Abrir chamado</span>
        <span class="sm:hidden">Abrir</span>
      </BaseButton>
    </template>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>
    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th">Número</th>
              <th class="th">Título</th>
              <th class="th">Categoria</th>
              <th class="th">Status</th>
              <th class="th">Aberto em</th>
              <th class="th">Última atualização</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="row in 4" :key="row">
                <td v-for="col in 6" :key="col" class="td">
                  <div class="h-3.5 w-24 animate-pulse rounded bg-gray-200" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!tickets.length">
              <td colspan="6" class="px-4 py-14 text-center">
                <p class="text-sm font-medium text-gray-900">Você ainda não tem chamados</p>
                <p class="mt-1 text-sm text-gray-500">Abra um chamado para falar com a nossa equipe.</p>
              </td>
            </tr>
            <tr v-for="ticket in tickets" v-else :key="ticket.id" class="hover:bg-gray-50">
              <td class="td whitespace-nowrap">
                <router-link
                  :to="{ name: 'customer.tickets.show', params: { id: ticket.id } }"
                  class="font-mono text-[13px] font-medium text-theme-light-700 hover:underline"
                >
                  {{ ticket.reference }}
                </router-link>
              </td>
              <td class="td max-w-72">
                <router-link
                  :to="{ name: 'customer.tickets.show', params: { id: ticket.id } }"
                  class="block truncate font-medium text-gray-900 hover:text-theme-light-700"
                >
                  {{ ticket.title }}
                </router-link>
              </td>
              <td class="td whitespace-nowrap text-gray-600">{{ ticket.category_label }}</td>
              <td class="td whitespace-nowrap">
                <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
              </td>
              <td class="td whitespace-nowrap text-[13px] text-gray-600">{{ formatDateTime(ticket.opened_at) }}</td>
              <td class="td whitespace-nowrap text-[13px] text-gray-600">{{ formatDateTime(ticket.updated_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="page = $event" />
    </div>

    <CustomerTicketFormDrawer :open="drawerOpen" @close="drawerOpen = false" @saved="onSaved" />
  </AppLayout>
</template>
