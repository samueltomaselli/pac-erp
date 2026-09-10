<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import { formatDate } from '@/constants/domain'
import { listCustomers } from '@/utils/api/customers'
import { listTasks } from '@/utils/api/tasks'

const counts = ref({ customers: 0, pending: 0, overdue: 0 })
const overdueTasks = ref([])
const upcomingTasks = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [customers, pending, overdue, upcoming] = await Promise.all([
      listCustomers({ per_page: 1, status: 'active' }),
      listTasks({ per_page: 1, status: 'pending' }),
      listTasks({ overdue: 1, per_page: 8 }),
      listTasks({ status: 'pending', due_from: new Date().toISOString().slice(0, 10), per_page: 8 }),
    ])

    counts.value = {
      customers: customers.meta.total,
      pending: pending.meta.total,
      overdue: overdue.meta.total,
    }
    overdueTasks.value = overdue.data
    upcomingTasks.value = upcoming.data
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <AppLayout title="Início" subtitle="O que precisa de atenção hoje">
    <div class="panel mb-4 flex flex-wrap divide-y divide-gray-200 sm:divide-x sm:divide-y-0">
      <div class="flex-1 px-5 py-3">
        <p class="text-[11px] font-medium text-gray-500">Clientes ativos</p>
        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">
          {{ counts.customers }}
        </p>
      </div>
      <div class="flex-1 px-5 py-3">
        <p class="text-[11px] font-medium text-gray-500">Tarefas pendentes</p>
        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ counts.pending }}</p>
      </div>
      <div class="flex-1 px-5 py-3">
        <p class="text-[11px] font-medium text-gray-500">Com prazo vencido</p>
        <p
          class="mt-0.5 text-lg font-semibold tabular-nums"
          :class="counts.overdue ? 'text-red-600' : 'text-gray-900'"
        >
          {{ counts.overdue }}
        </p>
      </div>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
      <section class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Prazo vencido</h2>
          <router-link
            :to="{ name: 'admin.tasks.index' }"
            class="text-xs font-medium text-theme-light-700 hover:underline"
          >
            Ver todas
          </router-link>
        </div>
        <div v-if="loading" class="divide-y divide-gray-200">
          <div v-for="n in 3" :key="n" class="px-5 py-3">
            <div class="h-3 w-48 animate-pulse rounded bg-gray-200" />
            <div class="mt-2 h-2.5 w-32 animate-pulse rounded bg-gray-200" />
          </div>
        </div>
        <p v-else-if="!overdueTasks.length" class="px-5 py-10 text-center text-sm text-gray-500">
          Nenhuma tarefa vencida. Tudo em dia.
        </p>
        <ul v-else class="divide-y divide-gray-200">
          <li
            v-for="task in overdueTasks"
            :key="task.id"
            class="flex items-center justify-between gap-3 bg-red-50 px-5 py-3"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-gray-900">{{ task.title }}</p>
              <p class="truncate text-xs text-gray-500">
                {{ task.customer?.name }} · prazo
                <span class="tabular-nums">{{ formatDate(task.due_date) }}</span>
              </p>
            </div>
            <BadgeTag :label="task.priority_label" :tone="task.priority" />
          </li>
        </ul>
      </section>
      <section class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Próximas</h2>
          <router-link
            :to="{ name: 'admin.tasks.index', query: { status: 'pending' } }"
            class="text-xs font-medium text-theme-light-700 hover:underline"
          >
            Ver todas
          </router-link>
        </div>
        <div v-if="loading" class="divide-y divide-gray-200">
          <div v-for="n in 3" :key="n" class="px-5 py-3">
            <div class="h-3 w-48 animate-pulse rounded bg-gray-200" />
            <div class="mt-2 h-2.5 w-32 animate-pulse rounded bg-gray-200" />
          </div>
        </div>
        <p v-else-if="!upcomingTasks.length" class="px-5 py-10 text-center text-sm text-gray-500">
          Nenhuma tarefa pendente com prazo à frente.
        </p>
        <ul v-else class="divide-y divide-gray-200">
          <li
            v-for="task in upcomingTasks"
            :key="task.id"
            class="flex items-center justify-between gap-3 px-5 py-3"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-gray-900">{{ task.title }}</p>
              <p class="truncate text-xs text-gray-500">
                {{ task.customer?.name }} · prazo
                <span class="tabular-nums">{{ formatDate(task.due_date) }}</span>
              </p>
            </div>
            <BadgeTag :label="task.priority_label" :tone="task.priority" />
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>