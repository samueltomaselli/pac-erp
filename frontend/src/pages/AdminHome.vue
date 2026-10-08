<script setup>
import { onMounted, ref } from 'vue'
import { AlertTriangle, CheckSquare, FileText, Plus, Users } from '@lucide/vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import TaskCheck from '@/components/TaskCheck.vue'
import TaskFormDrawer from '@/components/drawers/TaskFormDrawer.vue'
import { formatDate } from '@/constants/domain'
import { errorMessage, useToast } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { listProposals } from '@/utils/api/proposals'
import { completeTask, listTasks } from '@/utils/api/tasks'

const toast = useToast()

const counts = ref({ customers: 0, pending: 0, overdue: 0, sent: 0 })
const overdueTasks = ref([])
const upcomingTasks = ref([])
const loading = ref(true)
const taskDrawer = ref({ open: false, task: null })
const completingId = ref(null)

function localToday() {
  const now = new Date()
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset())

  return now.toISOString().slice(0, 10)
}

async function load() {
  try {
    const [customers, pending, overdue, upcoming, sent] = await Promise.all([
      listCustomers({ per_page: 1, status: 'active' }),
      listTasks({ per_page: 1, status: 'pending' }),
      listTasks({ overdue: 1, per_page: 8 }),
      listTasks({ status: 'pending', due_from: localToday(), per_page: 8 }),
      listProposals({ status: 'sent', per_page: 1 }).catch(() => ({ meta: { total: 0 } })),
    ])

    counts.value = {
      customers: customers.meta.total,
      pending: pending.meta.total,
      overdue: overdue.meta.total,
      sent: sent.meta?.total ?? 0,
    }
    overdueTasks.value = overdue.data
    upcomingTasks.value = upcoming.data
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível carregar o painel.'))
  } finally {
    loading.value = false
  }
}

async function onComplete(task) {
  completingId.value = task.id

  try {
    await completeTask(task.id)
    toast.success(`"${task.title}" concluída.`)
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível concluir a tarefa.'))
  } finally {
    completingId.value = null
  }
}

onMounted(load)
</script>
<template>
  <AppLayout title="Início" subtitle="O que precisa de atenção hoje">
    <template #actions>
      <BaseButton variant="secondary" class="max-sm:hidden!" :to="{ name: 'admin.proposals.create' }">
        <FileText class="size-4" />
        Nova proposta
      </BaseButton>
      <BaseButton @click="taskDrawer = { open: true, task: null }">
        <Plus class="size-4" />
        Nova tarefa
      </BaseButton>
    </template>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <router-link
        v-for="card in [
          { label: 'Clientes ativos', value: counts.customers, icon: Users, to: { name: 'admin.customers.index' } },
          { label: 'Tarefas pendentes', value: counts.pending, icon: CheckSquare, to: { name: 'admin.tasks.index', query: { status: 'pending' } } },
          {
            label: 'Com prazo vencido',
            value: counts.overdue,
            icon: AlertTriangle,
            to: { name: 'admin.tasks.index', query: { overdue: '1' } },
            danger: counts.overdue > 0,
          },
          { label: 'Propostas aguardando', value: counts.sent, icon: FileText, to: { name: 'admin.proposals.index', query: { status: 'sent' } } },
        ]"
        :key="card.label"
        :to="card.to"
        class="panel group flex items-start justify-between gap-3 p-4 transition-colors hover:border-gray-300 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/40"
      >
        <div>
          <p class="stat-label">{{ card.label }}</p>
          <p
            class="mt-1 text-2xl font-semibold tabular-nums"
            :class="card.danger ? 'text-red-600' : 'text-gray-900'"
          >
            <span v-if="loading" class="inline-block h-7 w-10 animate-pulse rounded bg-gray-200" />
            <template v-else>{{ card.value }}</template>
          </p>
        </div>
        <div
          class="flex size-9 shrink-0 items-center justify-center rounded-md transition-colors"
          :class="card.danger ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500 group-hover:text-gray-700'"
        >
          <component :is="card.icon" class="size-4" />
        </div>
      </router-link>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
      <section
        v-for="block in [
          {
            key: 'overdue',
            title: 'Prazo vencido',
            tasks: overdueTasks,
            empty: 'Nenhuma tarefa vencida. Tudo em dia.',
            to: { name: 'admin.tasks.index', query: { overdue: '1' } },
          },
          {
            key: 'upcoming',
            title: 'Próximas',
            tasks: upcomingTasks,
            empty: 'Nenhuma tarefa pendente com prazo à frente.',
            to: { name: 'admin.tasks.index', query: { status: 'pending' } },
          },
        ]"
        :key="block.key"
        class="panel overflow-hidden"
      >
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
            <span v-if="block.key === 'overdue'" class="size-2 rounded-full bg-red-500" />
            {{ block.title }}
          </h2>
          <router-link :to="block.to" class="link text-xs">Ver todas</router-link>
        </div>
        <div v-if="loading" class="divide-y divide-gray-100">
          <div v-for="n in 3" :key="n" class="flex gap-3 px-5 py-3.5">
            <div class="size-5 animate-pulse rounded-full bg-gray-200" />
            <div class="flex-1">
              <div class="h-3.5 w-48 animate-pulse rounded bg-gray-200" />
              <div class="mt-2 h-3 w-32 animate-pulse rounded bg-gray-200" />
            </div>
          </div>
        </div>
        <p v-else-if="!block.tasks.length" class="px-5 py-10 text-center text-sm text-gray-500">
          {{ block.empty }}
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li
            v-for="task in block.tasks"
            :key="task.id"
            class="flex items-start gap-3 px-5 py-3 transition-colors hover:bg-gray-50"
          >
            <TaskCheck class="mt-0.5" :loading="completingId === task.id" @toggle="onComplete(task)" />
            <button type="button" class="min-w-0 flex-1 text-left" @click="taskDrawer = { open: true, task }">
              <p class="truncate text-sm font-medium text-gray-900 hover:text-theme-light-700">{{ task.title }}</p>
              <p class="truncate text-xs text-gray-500">
                {{ task.customer?.name }} ·
                <span :class="block.key === 'overdue' && 'font-medium text-red-600'">
                  prazo <span class="tabular-nums">{{ formatDate(task.due_date) }}</span>
                </span>
              </p>
            </button>
            <BadgeTag :label="task.priority_label" :tone="task.priority" />
          </li>
        </ul>
      </section>
    </div>

    <TaskFormDrawer
      :open="taskDrawer.open"
      :task="taskDrawer.task"
      @close="taskDrawer.open = false"
      @saved="load"
    />
  </AppLayout>
</template>
