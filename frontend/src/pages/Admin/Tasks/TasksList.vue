<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Pencil, Plus, Trash2, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import TaskCheck from '@/components/TaskCheck.vue'
import TaskFormDrawer from '@/components/drawers/TaskFormDrawer.vue'
import { TASK_PRIORITIES, TASK_STATUSES, formatDate, formatDateTime } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { completeTask, deleteTask, listTasks, reopenTask } from '@/utils/api/tasks'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()

const tasks = ref([])
const meta = ref(null)
const customers = ref([])
const loading = ref(true)
const error = ref('')
const page = ref(1)
const togglingId = ref(null)

const blankFilters = () => ({
  customer_id: '',
  status: '',
  priority: '',
  due_from: '',
  due_until: '',
  overdue: false,
})

const filters = reactive({
  ...blankFilters(),
  customer_id: route.query.customer_id ? Number(route.query.customer_id) : '',
  status: TASK_STATUSES.some((s) => s.value === route.query.status) ? route.query.status : '',
  overdue: route.query.overdue === '1',
})

const hasFilters = computed(() => Object.values(filters).some((value) => value !== '' && value !== false))

const drawer = ref({ open: false, task: null })

async function fetchTasks() {
  loading.value = true
  error.value = ''

  try {
    const params = { page: page.value }

    for (const [key, value] of Object.entries(filters)) {
      if (value !== '' && value !== false) {
        params[key] = value === true ? 1 : value
      }
    }

    const response = await listTasks(params)
    tasks.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar as tarefas.')
  } finally {
    loading.value = false
  }
}

async function fetchCustomers() {
  try {
    const response = await listCustomers({ per_page: 100, status: 'active' })
    customers.value = response.data
  } catch {}
}

watch(filters, () => {
  page.value = 1
  fetchTasks()
})

watch(page, fetchTasks)

function clearFilters() {
  Object.assign(filters, blankFilters())
}

function openCreate() {
  drawer.value = { open: true, task: null }
}

function openEdit(task) {
  drawer.value = { open: true, task }
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

    await fetchTasks()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível atualizar a tarefa.'))
  } finally {
    togglingId.value = null
  }
}

async function onDelete(task) {
  const ok = await confirm({
    title: 'Remover tarefa',
    message: `"${task.title}" será removida permanentemente.`,
    confirmLabel: 'Remover',
    tone: 'danger',
  })

  if (!ok) {
    return
  }

  try {
    await deleteTask(task.id)
    toast.success('Tarefa removida.')
    fetchTasks()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível remover a tarefa.'))
  }
}

onMounted(() => {
  fetchCustomers()
  fetchTasks()
})
</script>
<template>
  <AppLayout title="Tarefas" subtitle="Agenda vinculada aos clientes">
    <template #actions>
      <BaseButton @click="openCreate">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Nova tarefa</span>
        <span class="sm:hidden">Nova</span>
      </BaseButton>
    </template>

    <div class="mb-4 space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-md border border-gray-300 bg-white p-0.5 shadow-xs" role="group">
          <button
            v-for="option in [{ value: '', label: 'Todas' }, ...TASK_STATUSES]"
            :key="option.value"
            type="button"
            class="h-8 rounded px-3 text-[13px] font-medium transition-colors"
            :class="
              filters.status === option.value && !filters.overdue
                ? 'bg-gray-900 text-white'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
            "
            @click="Object.assign(filters, { status: option.value, overdue: false })"
          >
            {{ option.label }}
          </button>
          <button
            type="button"
            class="h-8 rounded px-3 text-[13px] font-medium transition-colors"
            :class="filters.overdue ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'"
            @click="filters.overdue = !filters.overdue"
          >
            Vencidas
          </button>
        </div>
        <select v-model="filters.customer_id" class="field w-auto min-w-48 flex-1 sm:flex-none" aria-label="Cliente">
          <option value="">Todos os clientes</option>
          <option v-for="customer in customers" :key="customer.id" :value="customer.id">
            {{ customer.name }}
          </option>
        </select>
        <select v-model="filters.priority" class="field w-auto min-w-40" aria-label="Prioridade">
          <option value="">Qualquer prioridade</option>
          <option v-for="option in TASK_PRIORITIES" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <div class="flex items-center gap-1.5">
          <label class="sr-only" for="due_from">Prazo de</label>
          <input id="due_from" v-model="filters.due_from" type="date" class="field w-auto" title="Prazo a partir de" />
          <span class="text-xs text-gray-400">até</span>
          <label class="sr-only" for="due_until">Prazo até</label>
          <input id="due_until" v-model="filters.due_until" type="date" class="field w-auto" title="Prazo até" />
        </div>
        <BaseButton v-if="hasFilters" variant="ghost" @click="clearFilters">
          <X class="size-4" />
          Limpar
        </BaseButton>
      </div>
    </div>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>

    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th w-px pr-0"><span class="sr-only">Concluída</span></th>
              <th class="th">Tarefa</th>
              <th class="th">Cliente</th>
              <th class="th">Prazo</th>
              <th class="th">Prioridade</th>
              <th class="th w-px"><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="n in 5" :key="`skeleton-${n}`">
                <td class="td pr-0"><div class="size-5 animate-pulse rounded-full bg-gray-200" /></td>
                <td v-for="col in 5" :key="col" class="td">
                  <div class="h-3.5 animate-pulse rounded bg-gray-200" :class="col === 1 ? 'w-52' : 'w-20'" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!tasks.length">
              <td class="px-4 py-14 text-center" colspan="6">
                <p class="text-sm font-medium text-gray-900">Nenhuma tarefa encontrada</p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ hasFilters ? 'Ajuste ou limpe os filtros.' : 'Crie uma tarefa para começar a agenda.' }}
                </p>
                <BaseButton v-if="hasFilters" variant="secondary" class="mt-4" @click="clearFilters">
                  Limpar filtros
                </BaseButton>
                <BaseButton v-else class="mt-4" @click="openCreate"><Plus class="size-4" /> Nova tarefa</BaseButton>
              </td>
            </tr>
            <tr
              v-for="task in tasks"
              v-else
              :key="task.id"
              class="group transition-colors"
              :class="task.is_overdue ? 'bg-red-50/60 hover:bg-red-50' : 'hover:bg-gray-50'"
            >
              <td class="td pr-0">
                <TaskCheck
                  :done="task.status === 'completed'"
                  :loading="togglingId === task.id"
                  @toggle="onToggle(task)"
                />
              </td>
              <td class="td max-w-96">
                <button type="button" class="block w-full min-w-0 text-left" @click="openEdit(task)">
                  <span
                    class="block truncate font-medium group-hover:text-theme-light-700"
                    :class="task.status === 'completed' ? 'text-gray-500 line-through' : 'text-gray-900'"
                  >
                    {{ task.title }}
                  </span>
                  <span v-if="task.description" class="block truncate text-xs text-gray-500">
                    {{ task.description }}
                  </span>
                </button>
              </td>
              <td class="td max-w-56">
                <router-link
                  :to="{ name: 'admin.customers.show', params: { id: task.customer_id } }"
                  class="block truncate text-gray-700 hover:text-theme-light-700 hover:underline"
                >
                  {{ task.customer?.name }}
                </router-link>
              </td>
              <td class="td whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <span class="tabular-nums" :class="task.is_overdue ? 'font-medium text-red-600' : 'text-gray-700'">
                    {{ formatDate(task.due_date) }}
                  </span>
                  <BadgeTag v-if="task.is_overdue" label="Vencida" tone="high" />
                </div>
                <p v-if="task.completed_at" class="text-xs text-gray-500">
                  Concluída em {{ formatDateTime(task.completed_at) }}
                </p>
              </td>
              <td class="td whitespace-nowrap">
                <BadgeTag :label="task.priority_label" :tone="task.priority" />
              </td>
              <td class="td whitespace-nowrap">
                <div class="flex justify-end gap-0.5">
                  <BaseButton variant="ghost" size="sm" icon label="Editar" @click="openEdit(task)">
                    <Pencil class="size-4" />
                  </BaseButton>
                  <BaseButton variant="ghost-danger" size="sm" icon label="Remover" @click="onDelete(task)">
                    <Trash2 class="size-4" />
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="page = $event" />
    </div>

    <TaskFormDrawer
      :open="drawer.open"
      :task="drawer.task"
      :customer-id="!drawer.task && filters.customer_id ? filters.customer_id : ''"
      :customers="customers"
      @close="drawer.open = false"
      @saved="fetchTasks"
    />
  </AppLayout>
</template>
