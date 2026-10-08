<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FilePlus, Pencil, Plus } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import TaskCheck from '@/components/TaskCheck.vue'
import CustomerFormDrawer from '@/components/drawers/CustomerFormDrawer.vue'
import TaskFormDrawer from '@/components/drawers/TaskFormDrawer.vue'
import { formatCurrency, formatDate, formatDateTime } from '@/constants/domain'
import { errorMessage, useToast } from '@/composables/useToast'
import { getCustomer } from '@/utils/api/customers'
import { completeTask, reopenTask } from '@/utils/api/tasks'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const customer = ref(null)
const loading = ref(true)
const error = ref('')
const editOpen = ref(route.query.editar === '1')
const taskDrawer = ref({ open: false, task: null })
const togglingId = ref(null)

async function fetchCustomer({ silent = false } = {}) {
  if (!silent) {
    loading.value = true
  }

  error.value = ''

  try {
    customer.value = await getCustomer(route.params.id)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar o cliente.')
  } finally {
    loading.value = false
  }
}

async function onToggle(task) {
  togglingId.value = task.id

  try {
    if (task.status === 'pending') {
      await completeTask(task.id)
      toast.success('Tarefa concluída.')
    } else {
      await reopenTask(task.id)
      toast.info('Tarefa reaberta.')
    }

    await fetchCustomer({ silent: true })
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível atualizar a tarefa.'))
  } finally {
    togglingId.value = null
  }
}

function closeEdit() {
  editOpen.value = false

  if (route.query.editar) {
    router.replace({ query: {} })
  }
}

function openTask(task = null) {
  taskDrawer.value = { open: true, task: task ? { ...task, customer: { id: customer.value.id, name: customer.value.name } } : null }
}

onMounted(fetchCustomer)
</script>
<template>
  <AppLayout
    :title="customer?.name ?? 'Cliente'"
    subtitle="Dados cadastrais, tarefas e propostas"
    :back="{ name: 'admin.customers.index' }"
  >
    <template #actions>
      <template v-if="customer">
        <BaseButton variant="secondary" class="max-md:hidden!" @click="editOpen = true">
          <Pencil class="size-4" />
          Editar
        </BaseButton>
        <BaseButton variant="secondary" icon label="Editar" class="md:hidden!" @click="editOpen = true">
          <Pencil class="size-4" />
        </BaseButton>
        <BaseButton
          variant="secondary"
          class="max-sm:hidden!"
          :to="{ name: 'admin.proposals.create', query: { customer_id: customer.id } }"
        >
          <FilePlus class="size-4" />
          Nova proposta
        </BaseButton>
        <BaseButton @click="openTask()">
          <Plus class="size-4" />
          <span class="hidden sm:inline">Nova tarefa</span>
          <span class="sm:hidden">Tarefa</span>
        </BaseButton>
      </template>
    </template>

    <div v-if="loading" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="panel h-72 animate-pulse bg-gray-100 lg:col-span-2" />
      <div class="panel h-72 animate-pulse bg-gray-100" />
    </div>
    <AlertBanner v-else-if="error">{{ error }}</AlertBanner>

    <div v-else-if="customer" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
      <aside class="space-y-4 lg:order-last">
        <section class="panel p-5">
          <div class="mb-4 flex flex-wrap items-center gap-2">
            <h2 class="text-sm font-semibold text-gray-900">Dados cadastrais</h2>
            <BadgeTag v-if="customer.deleted_at" label="Inativado" tone="inactive" />
            <BadgeTag v-else :label="customer.status_label" :tone="customer.status" />
          </div>
          <dl class="space-y-3.5">
            <div>
              <dt class="stat-label">CNPJ/CPF</dt>
              <dd class="mono mt-0.5 text-sm text-gray-900">{{ customer.document_formatted }}</dd>
            </div>
            <div>
              <dt class="stat-label">E-mail</dt>
              <dd class="mt-0.5 truncate text-sm">
                <a :href="`mailto:${customer.email}`" class="link font-normal">{{ customer.email }}</a>
              </dd>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <dt class="stat-label">Telefone</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ customer.phone || '—' }}</dd>
              </div>
              <div>
                <dt class="stat-label">Segmento</dt>
                <dd class="mt-0.5 text-sm text-gray-900">{{ customer.segment_label }}</dd>
              </div>
            </div>
            <div>
              <dt class="stat-label">Contato</dt>
              <dd class="mt-0.5 text-sm text-gray-900">{{ customer.contact_name || '—' }}</dd>
            </div>
            <div>
              <dt class="stat-label">Cadastrado em</dt>
              <dd class="mt-0.5 text-sm text-gray-900">{{ formatDateTime(customer.created_at) }}</dd>
            </div>
          </dl>
        </section>

        <section class="panel p-5">
          <h2 class="stat-label">Plano atual</h2>
          <template v-if="customer.current_plan">
            <p class="mt-1 font-display text-base font-semibold text-gray-900">
              {{ customer.current_plan.title }}
            </p>
            <p class="mono text-xs text-gray-500">{{ customer.current_plan.reference }}</p>
            <p class="mt-3 text-xl font-semibold tabular-nums text-gray-900">
              {{ formatCurrency(customer.current_plan.mrr_cents) }}
              <span class="text-sm font-normal text-gray-500">/mês</span>
            </p>
            <p class="mt-1 text-xs text-gray-500">
              Ativo desde {{ formatDateTime(customer.current_plan.accepted_at) }}
            </p>
          </template>
          <p v-else class="mt-1 text-sm text-gray-500">Nenhum plano ativo.</p>
        </section>
      </aside>

      <div class="space-y-4 lg:col-span-2">
        <section class="panel overflow-hidden">
          <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">
              Tarefas
              <span class="ml-1 text-xs font-normal text-gray-500">
                <span class="tabular-nums">{{ customer.pending_tasks_count }}</span> pendente(s) de
                <span class="tabular-nums">{{ customer.tasks_count }}</span>
              </span>
            </h2>
            <router-link
              :to="{ name: 'admin.tasks.index', query: { customer_id: customer.id } }"
              class="link text-xs"
            >
              Ver na agenda
            </router-link>
          </div>
          <div v-if="!customer.tasks.length" class="px-5 py-10 text-center">
            <p class="text-sm text-gray-500">Nenhuma tarefa vinculada a este cliente.</p>
            <BaseButton variant="secondary" size="sm" class="mt-3" @click="openTask()">
              <Plus class="size-3.5" /> Criar tarefa
            </BaseButton>
          </div>
          <ul v-else class="divide-y divide-gray-100">
            <li
              v-for="task in customer.tasks"
              :key="task.id"
              class="flex items-start gap-3 px-5 py-3"
              :class="task.is_overdue ? 'bg-red-50/60' : ''"
            >
              <TaskCheck
                :done="task.status === 'completed'"
                :loading="togglingId === task.id"
                class="mt-0.5"
                @toggle="onToggle(task)"
              />
              <button type="button" class="min-w-0 flex-1 text-left" @click="openTask(task)">
                <p
                  class="text-sm font-medium hover:text-theme-light-700"
                  :class="task.status === 'completed' ? 'text-gray-500 line-through' : 'text-gray-900'"
                >
                  {{ task.title }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                  <span :class="task.is_overdue && 'font-medium text-red-600'">
                    Prazo {{ formatDate(task.due_date) }}
                  </span>
                  <span v-if="task.completed_at"> · Concluída em {{ formatDateTime(task.completed_at) }}</span>
                </p>
              </button>
              <div class="flex shrink-0 items-center gap-1.5">
                <BadgeTag v-if="task.is_overdue" label="Vencida" tone="high" />
                <BadgeTag :label="task.priority_label" :tone="task.priority" />
              </div>
            </li>
          </ul>
        </section>

        <section class="panel overflow-hidden">
          <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">
              Propostas
              <span class="ml-1 text-xs font-normal tabular-nums text-gray-500">{{ customer.proposals_count }}</span>
            </h2>
            <router-link
              :to="{ name: 'admin.proposals.create', query: { customer_id: customer.id } }"
              class="link text-xs"
            >
              Nova proposta
            </router-link>
          </div>
          <p v-if="!customer.proposals?.length" class="px-5 py-10 text-center text-sm text-gray-500">
            Nenhuma proposta para este cliente.
          </p>
          <ul v-else class="divide-y divide-gray-100">
            <li v-for="proposal in customer.proposals" :key="proposal.id">
              <router-link
                :to="{ name: 'admin.proposals.show', params: { id: proposal.id } }"
                class="flex items-center gap-4 px-5 py-3 transition-colors hover:bg-gray-50"
              >
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ proposal.title }}</p>
                  <p class="mt-0.5 text-xs text-gray-500">
                    <span class="mono">{{ proposal.reference }}</span>
                    · emitida {{ formatDate(proposal.issued_on) }} · válida até
                    {{ formatDate(proposal.valid_until) }}
                  </p>
                </div>
                <BadgeTag :label="proposal.status_label" :tone="proposal.status" />
                <span class="w-28 shrink-0 text-right text-sm font-medium tabular-nums text-gray-900">
                  {{ formatCurrency(proposal.totals.total_cents) }}
                </span>
              </router-link>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <CustomerFormDrawer
      :open="editOpen"
      :customer-id="customer?.id ?? route.params.id"
      @close="closeEdit"
      @saved="fetchCustomer({ silent: true })"
    />
    <TaskFormDrawer
      :open="taskDrawer.open"
      :task="taskDrawer.task"
      :customer-id="customer?.id ?? ''"
      lock-customer
      @close="taskDrawer.open = false"
      @saved="fetchCustomer({ silent: true })"
    />
  </AppLayout>
</template>
