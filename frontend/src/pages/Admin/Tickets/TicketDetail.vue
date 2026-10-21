<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import FormField from '@/components/FormField.vue'
import TicketConversation from '@/components/TicketConversation.vue'
import { TICKET_PRIORITIES, formatDateTime } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import {
  getTicket,
  listStaff,
  transitionTicket,
  updateTicket,
  updateTicketAssignee,
} from '@/utils/api/tickets'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()
const ticket = ref(null)
const staff = ref([])
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const saving = ref(false)

const transitions = computed(() => ticket.value?.allowed_transitions ?? [])

async function loadTicket() {
  loading.value = true
  ticket.value = null
  error.value = ''
  try {
    ticket.value = await getTicket(route.params.id)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar o chamado.')
  } finally {
    loading.value = false
  }
}

async function loadStaff() {
  try {
    staff.value = await listStaff()
  } catch (e) {
    actionError.value = errorMessage(e, 'Não foi possível carregar a lista de responsáveis.')
  }
}

async function changeStatus(transition) {
  if (transition.status === 'closed') {
    const confirmed = await confirm({
      title: 'Fechar chamado',
      message: 'O fechamento é irreversível. O chamado não poderá receber novos comentários.',
      confirmLabel: 'Fechar chamado',
      tone: 'danger',
    })
    if (!confirmed) return
  }

  saving.value = true
  actionError.value = ''
  try {
    await transitionTicket(ticket.value.id, transition.status)
    toast.success(`Chamado atualizado para "${transition.label}".`)
    await loadTicket()
  } catch (e) {
    actionError.value = errorMessage(e, 'Não foi possível alterar o status.')
  } finally {
    saving.value = false
  }
}

async function changePriority(event) {
  const priority = event.target.value
  actionError.value = ''
  try {
    await updateTicket(ticket.value.id, {
      title: ticket.value.title,
      category: ticket.value.category,
      description: ticket.value.description,
      priority,
    })
    toast.success('Prioridade atualizada.')
    await loadTicket()
  } catch (e) {
    actionError.value = errorMessage(e, 'Não foi possível atualizar a prioridade.')
    await loadTicket()
  }
}

async function changeAssignee(event) {
  const assignedTo = event.target.value === '' ? null : Number(event.target.value)
  actionError.value = ''
  try {
    await updateTicketAssignee(ticket.value.id, assignedTo)
    toast.success(assignedTo ? 'Responsável atribuído.' : 'Responsável removido.')
    await loadTicket()
  } catch (e) {
    actionError.value = errorMessage(e, 'Não foi possível atualizar o responsável.')
    await loadTicket()
  }
}

watch(() => route.params.id, loadTicket, { immediate: true })
onMounted(loadStaff)
</script>

<template>
  <AppLayout
    :title="ticket ? ticket.reference : 'Chamado'"
    :subtitle="ticket?.title || 'Detalhes da solicitação'"
    :back="{ name: 'admin.tickets.index' }"
    width="wide"
  >
    <div v-if="loading" class="panel space-y-4 p-6">
      <div class="h-5 w-56 animate-pulse rounded bg-gray-200" />
      <div class="h-24 animate-pulse rounded bg-gray-100" />
    </div>
    <AlertBanner v-else-if="error">{{ error }}</AlertBanner>
    <div v-else-if="ticket" class="space-y-4">
      <AlertBanner v-if="actionError">{{ actionError }}</AlertBanner>
      <section class="panel p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono text-[13px] font-medium text-gray-500">{{ ticket.reference }}</span>
              <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
              <BadgeTag :label="ticket.priority_label" :tone="ticket.priority" />
            </div>
            <h1 class="mt-2 wrap-break-word text-xl font-semibold text-gray-900">{{ ticket.title }}</h1>
            <p class="mt-1 text-[13px] text-gray-500">
              {{ ticket.customer?.name }} · {{ ticket.category_label }} · Aberto em
              {{ formatDateTime(ticket.opened_at) }}
            </p>
          </div>
          <div v-if="transitions.length" class="flex flex-wrap gap-2">
            <BaseButton
              v-for="transition in transitions"
              :key="transition.status"
              :variant="transition.status === 'closed' ? 'danger' : 'secondary'"
              size="sm"
              class="!shadow-none"
              :disabled="saving"
              @click="changeStatus(transition)"
            >
              {{ transition.label }}
            </BaseButton>
          </div>
          <p v-else class="text-[13px] text-gray-500">Este chamado não possui transições disponíveis.</p>
        </div>
        <div class="mt-5 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
          <FormField label="Responsável" name="assignee">
            <select
              id="assignee"
              :value="ticket.assignee?.id ?? ''"
              class="field !h-8 !text-[13px] !leading-8 !shadow-none"
              aria-label="Responsável pelo chamado"
              @change="changeAssignee"
            >
              <option value="">Sem responsável</option>
              <option v-for="person in staff" :key="person.id" :value="person.id">
                {{ person.name }}
              </option>
            </select>
          </FormField>
          <FormField label="Prioridade" name="priority">
            <select
              id="priority"
              :value="ticket.priority"
              class="field !h-8 !text-[13px] !leading-8 !shadow-none"
              aria-label="Prioridade do chamado"
              @change="changePriority"
            >
              <option v-for="priority in TICKET_PRIORITIES" :key="priority.value" :value="priority.value">
                {{ priority.label }}
              </option>
            </select>
          </FormField>
        </div>
        <div class="mt-4 border-t border-gray-100 pt-4">
          <h2 class="text-[13px] font-semibold text-gray-700">Descrição</h2>
          <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-700">{{ ticket.description }}</p>
        </div>
      </section>
      <TicketConversation :ticket="ticket" @updated="loadTicket" />
    </div>
  </AppLayout>
</template>
