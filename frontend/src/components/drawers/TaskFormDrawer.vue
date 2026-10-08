<script setup>
import { computed, reactive, ref, watch } from 'vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDrawer from '@/components/BaseDrawer.vue'
import FormField from '@/components/FormField.vue'
import { TASK_PRIORITIES } from '@/constants/domain'
import { useToast } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { createTask, updateTask } from '@/utils/api/tasks'

const props = defineProps({
  open: { type: Boolean, default: false },
  task: { type: Object, default: null },
  customerId: { type: [Number, String], default: '' },
  customers: { type: Array, default: null },
  lockCustomer: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const ownCustomers = ref([])
const errors = ref({})
const message = ref('')
const submitting = ref(false)
const keepOpen = ref(false)

const form = reactive({
  customer_id: '',
  title: '',
  description: '',
  due_date: '',
  priority: 'medium',
})

const isEditing = computed(() => Boolean(props.task))
const customerOptions = computed(() => props.customers ?? ownCustomers.value)
const lockedCustomer = computed(() => isEditing.value || props.lockCustomer)

function today() {
  const now = new Date()
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset())

  return now.toISOString().slice(0, 10)
}

function reset() {
  Object.assign(form, {
    customer_id: props.task?.customer_id ?? (props.customerId ? Number(props.customerId) : ''),
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    due_date: props.task?.due_date ?? today(),
    priority: props.task?.priority ?? 'medium',
  })
}

watch(
  () => props.open,
  async (open) => {
    if (!open) {
      return
    }

    errors.value = {}
    message.value = ''
    reset()

    if (!props.customers && !ownCustomers.value.length) {
      try {
        const response = await listCustomers({ per_page: 100, status: 'active' })
        ownCustomers.value = response.data
      } catch {
        message.value = 'Não foi possível carregar os clientes.'
      }
    }
  },
  { immediate: true },
)

async function onSubmit() {
  errors.value = {}
  message.value = ''
  submitting.value = true

  try {
    if (isEditing.value) {
      const { customer_id, ...payload } = form
      const task = await updateTask(props.task.id, payload)
      toast.success('Tarefa atualizada.')
      emit('saved', task)
      emit('close')

      return
    }

    const task = await createTask({ ...form })
    toast.success('Tarefa criada.')
    emit('saved', task)

    if (keepOpen.value) {
      form.title = ''
      form.description = ''

      return
    }

    emit('close')
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {}
    message.value = e?.response?.data?.message || 'Não foi possível salvar a tarefa.'
  } finally {
    submitting.value = false
  }
}
</script>
<template>
  <BaseDrawer
    :open="open"
    :title="isEditing ? 'Editar tarefa' : 'Nova tarefa'"
    :description="isEditing ? task?.customer?.name : 'Agende uma ação para um cliente'"
    @close="emit('close')"
  >
    <form id="task-form" class="space-y-4" @submit.prevent="onSubmit">
      <AlertBanner v-if="message">{{ message }}</AlertBanner>
      <FormField label="Cliente" name="customer_id" :errors="errors" required>
        <select
          id="customer_id"
          v-model="form.customer_id"
          required
          class="field"
          :disabled="lockedCustomer"
          :class="errors.customer_id && 'field-error'"
        >
          <option value="" disabled>Selecione…</option>
          <option v-if="isEditing && task?.customer" :value="task.customer_id">
            {{ task.customer.name }}
          </option>
          <option
            v-for="customer in customerOptions.filter((c) => !isEditing || c.id !== task?.customer_id)"
            :key="customer.id"
            :value="customer.id"
          >
            {{ customer.name }}
          </option>
        </select>
      </FormField>
      <FormField label="Título" name="title" :errors="errors" required>
        <input
          id="title"
          v-model="form.title"
          type="text"
          required
          placeholder="Ex.: Enviar contrato assinado"
          class="field"
          :class="errors.title && 'field-error'"
        />
      </FormField>
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Prazo" name="due_date" :errors="errors" required>
          <input
            id="due_date"
            v-model="form.due_date"
            type="date"
            required
            class="field"
            :class="errors.due_date && 'field-error'"
          />
        </FormField>
        <FormField label="Prioridade" name="priority" :errors="errors">
          <div class="grid grid-cols-3 gap-1 rounded-md bg-gray-100 p-1" role="radiogroup">
            <button
              v-for="option in TASK_PRIORITIES"
              :key="option.value"
              type="button"
              role="radio"
              :aria-checked="form.priority === option.value"
              class="h-7 rounded text-[13px] font-medium transition-colors"
              :class="
                form.priority === option.value
                  ? 'bg-white text-gray-900 shadow-xs'
                  : 'text-gray-500 hover:text-gray-800'
              "
              @click="form.priority = option.value"
            >
              {{ option.label }}
            </button>
          </div>
        </FormField>
      </div>
      <FormField label="Descrição" name="description" :errors="errors">
        <textarea
          id="description"
          v-model="form.description"
          rows="5"
          placeholder="Detalhes, contexto ou próximos passos"
          class="field"
        />
      </FormField>
    </form>
    <template #footer>
      <label v-if="!isEditing" class="mr-auto flex items-center gap-2 text-[13px] text-gray-600">
        <input v-model="keepOpen" type="checkbox" class="checkbox" />
        Criar outra em seguida
      </label>
      <BaseButton variant="secondary" @click="emit('close')">Cancelar</BaseButton>
      <BaseButton type="submit" form="task-form" :loading="submitting">
        {{ isEditing ? 'Salvar alterações' : 'Criar tarefa' }}
      </BaseButton>
    </template>
  </BaseDrawer>
</template>
