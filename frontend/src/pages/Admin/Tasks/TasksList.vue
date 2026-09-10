<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Plus } from '@lucide/vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import FormField from '@/components/FormField.vue'
import { TASK_PRIORITIES, TASK_STATUSES, formatDate, formatDateTime } from '@/constants/domain'
import { listCustomers } from '@/utils/api/customers'
import { completeTask, createTask, deleteTask, listTasks, reopenTask } from '@/utils/api/tasks'

const route = useRoute()

const customerFromQuery = route.query.customer_id ? Number(route.query.customer_id) : ''

const tasks = ref([])
const meta = ref(null)
const customers = ref([])
const loading = ref(true)
const error = ref('')
const page = ref(1)

const filters = reactive({
  customer_id: customerFromQuery,
  status: '',
  priority: '',
  due_from: '',
  due_until: '',
  overdue: false,
})

const showForm = ref(false)
const formErrors = ref({})
const submitting = ref(false)
const form = reactive({
  customer_id: customerFromQuery,
  title: '',
  description: '',
  due_date: '',
  priority: 'medium',
})

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
    error.value = e?.response?.data?.message || 'Não foi possível carregar as tarefas.'
  } finally {
    loading.value = false
  }
}

async function fetchCustomers() {
  const response = await listCustomers({ per_page: 100, status: 'active' })
  customers.value = response.data
}

watch(filters, () => {
  page.value = 1
  fetchTasks()
})

watch(page, fetchTasks)

async function onCreate() {
  formErrors.value = {}
  submitting.value = true

  try {
    await createTask({ ...form })
    form.title = ''
    form.description = ''
    form.due_date = ''
    form.priority = 'medium'
    showForm.value = false
    fetchTasks()
  } catch (e) {
    formErrors.value = e?.response?.data?.errors ?? {}
  } finally {
    submitting.value = false
  }
}

async function onToggle(task) {
  if (task.status === 'pending') {
    await completeTask(task.id)
  } else {
    await reopenTask(task.id)
  }

  fetchTasks()
}

async function onDelete(task) {
  if (!window.confirm(`Remover a tarefa "${task.title}"?`)) {
    return
  }

  await deleteTask(task.id)
  fetchTasks()
}

onMounted(() => {
  fetchCustomers()
  fetchTasks()
})
</script>
<template>
  <AppLayout title="Tarefas" subtitle="Agenda vinculada aos clientes">
    <template #actions>
      <BaseButton type="button" @click="showForm = !showForm">
        <Plus class="size-3.5" />
        {{ showForm ? 'Cancelar' : 'Nova tarefa' }}
      </BaseButton>
    </template>
    <form v-if="showForm" class="panel mb-4 p-6" @submit.prevent="onCreate">
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Cliente" name="customer_id" :errors="formErrors">
          <select id="customer_id" v-model="form.customer_id" required class="field">
            <option value="" disabled>Selecione…</option>
            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
              {{ customer.name }}
            </option>
          </select>
        </FormField>
        <FormField label="Título" name="title" :errors="formErrors">
          <input id="title" v-model="form.title" type="text" required class="field" />
        </FormField>
        <FormField label="Prazo" name="due_date" :errors="formErrors">
          <input id="due_date" v-model="form.due_date" type="date" required class="field" />
        </FormField>
        <FormField label="Prioridade" name="priority" :errors="formErrors">
          <select id="priority" v-model="form.priority" class="field">
            <option v-for="option in TASK_PRIORITIES" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </FormField>
        <div class="sm:col-span-2">
          <FormField label="Descrição" name="description" :errors="formErrors">
            <textarea id="description" v-model="form.description" rows="3" class="field" />
          </FormField>
        </div>
      </div>
      <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
        <BaseButton type="submit" :disabled="submitting">Criar tarefa</BaseButton>
      </div>
    </form>
    <div class="panel mb-4 p-3">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <label class="field-label" for="filter-customer">Cliente</label>
          <select id="filter-customer" v-model="filters.customer_id" class="field">
            <option value="">Todos</option>
            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
              {{ customer.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="field-label" for="filter-status">Situação</label>
          <select id="filter-status" v-model="filters.status" class="field">
            <option value="">Todas</option>
            <option v-for="option in TASK_STATUSES" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="field-label" for="filter-priority">Prioridade</label>
          <select id="filter-priority" v-model="filters.priority" class="field">
            <option value="">Todas</option>
            <option v-for="option in TASK_PRIORITIES" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="field-label" for="due_from">Prazo de</label>
          <input id="due_from" v-model="filters.due_from" type="date" class="field" />
        </div>
        <div>
          <label class="field-label" for="due_until">Prazo até</label>
          <input id="due_until" v-model="filters.due_until" type="date" class="field" />
        </div>
      </div>
      <label class="mt-3 flex w-fit items-center gap-2 text-xs text-gray-600">
        <input
          v-model="filters.overdue"
          type="checkbox"
          class="size-3.5 rounded border-gray-300 text-theme-light-600 focus:ring-theme-light-500/30"
        />
        Somente tarefas com prazo vencido
      </label>
    </div>
    <p
      v-if="error"
      class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
    >
      {{ error }}
    </p>
    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left">
            <tr class="border-b border-gray-200">
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Tarefa</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Cliente</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Prazo</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Prioridade</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Situação</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] font-medium text-gray-500">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <template v-if="loading">
              <tr v-for="n in 4" :key="`skeleton-${n}`">
                <td v-for="col in 6" :key="col" class="px-4 py-3">
                  <div
                    class="h-3 animate-pulse rounded bg-gray-200"
                    :class="col === 1 ? 'w-48' : 'w-20'"
                  />
                </td>
              </tr>
            </template>
            <tr v-else-if="!tasks.length">
              <td class="px-4 py-10 text-center text-sm text-gray-500" colspan="6">
                Nenhuma tarefa encontrada.
              </td>
            </tr>
            <tr
              v-for="task in tasks"
              v-else
              :key="task.id"
              :class="task.is_overdue ? 'bg-red-50 hover:bg-red-100/70' : 'hover:bg-gray-50'"
            >
              <td class="max-w-[22rem] px-4 py-3">
                <p class="truncate font-medium text-gray-900">{{ task.title }}</p>
                <p v-if="task.description" class="truncate text-xs text-gray-500">
                  {{ task.description }}
                </p>
              </td>
              <td class="max-w-[14rem] px-4 py-3">
                <router-link
                  :to="{ name: 'admin.customers.show', params: { id: task.customer_id } }"
                  class="block truncate text-gray-700 hover:text-theme-light-700 hover:underline"
                >
                  {{ task.customer?.name }}
                </router-link>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <div class="flex items-center gap-1.5">
                  <span
                    class="tabular-nums"
                    :class="task.is_overdue ? 'font-medium text-red-600' : 'text-gray-700'"
                  >
                    {{ formatDate(task.due_date) }}
                  </span>
                  <BadgeTag v-if="task.is_overdue" label="Vencida" tone="high" />
                </div>
                <p v-if="task.completed_at" class="text-xs text-gray-500">
                  Concluída em {{ formatDateTime(task.completed_at) }}
                </p>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <BadgeTag :label="task.priority_label" :tone="task.priority" />
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <BadgeTag :label="task.status_label" :tone="task.status" />
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <div class="flex justify-end gap-1">
                  <BaseButton type="button" variant="ghost" size="sm" @click="onToggle(task)">
                    {{ task.status === 'pending' ? 'Concluir' : 'Reabrir' }}
                  </BaseButton>
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:bg-red-50"
                    @click="onDelete(task)"
                  >
                    Remover
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-between">
      <span class="text-xs text-gray-500">
        Página <span class="tabular-nums">{{ meta.current_page }}</span> de
        <span class="tabular-nums">{{ meta.last_page }}</span>
      </span>
      <div class="flex gap-2">
        <BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1"
          @click="page -= 1"
        >
          Anterior
        </BaseButton>
        <BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="page += 1"
        >
          Próxima
        </BaseButton>
      </div>
    </div>
  </AppLayout>
</template>