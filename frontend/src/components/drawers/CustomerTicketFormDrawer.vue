<script setup>
import { reactive, ref, watch } from 'vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDrawer from '@/components/BaseDrawer.vue'
import FormField from '@/components/FormField.vue'
import { TICKET_CATEGORIES } from '@/constants/domain'
import { useToast } from '@/composables/useToast'
import { createCustomerTicket } from '@/utils/api/customerTickets'

const props = defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'saved'])
const toast = useToast()
const form = reactive({ title: '', category: '', description: '' })
const errors = ref({})
const message = ref('')
const submitting = ref(false)

watch(
  () => props.open,
  (open) => {
    if (!open) return
    Object.assign(form, { title: '', category: '', description: '' })
    errors.value = {}
    message.value = ''
  },
)

async function onSubmit() {
  errors.value = {}
  message.value = ''
  submitting.value = true
  try {
    const ticket = await createCustomerTicket({ ...form })
    toast.success('Chamado aberto.')
    emit('saved', ticket)
    emit('close')
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {}
    message.value = e?.response?.data?.message || 'Não foi possível abrir o chamado.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <BaseDrawer
    :open="open"
    title="Abrir chamado"
    description="Conte à nossa equipe como podemos ajudar"
    @close="emit('close')"
  >
    <form id="customer-ticket-form" class="space-y-4" @submit.prevent="onSubmit">
      <AlertBanner v-if="message">{{ message }}</AlertBanner>
      <FormField label="Título" name="title" :errors="errors" required>
        <input
          id="title"
          v-model="form.title"
          type="text"
          required
          maxlength="255"
          class="field !h-8 !text-[13px] !leading-8 !shadow-none"
          placeholder="Resuma o assunto do chamado"
        />
      </FormField>
      <FormField label="Categoria" name="category" :errors="errors" required>
        <select id="category" v-model="form.category" required class="field !h-8 !text-[13px] !leading-8 !shadow-none">
          <option value="" disabled>Selecione…</option>
          <option v-for="option in TICKET_CATEGORIES" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </FormField>
      <FormField label="Descrição" name="description" :errors="errors" required>
        <textarea
          id="description"
          v-model="form.description"
          rows="6"
          required
          class="field !h-auto !text-[13px] !leading-normal !shadow-none"
          placeholder="Descreva sua dúvida, problema ou solicitação"
        />
      </FormField>
    </form>
    <template #footer>
      <BaseButton variant="secondary" size="sm" class="!shadow-none" @click="emit('close')">Cancelar</BaseButton>
      <BaseButton type="submit" form="customer-ticket-form" size="sm" class="!shadow-none" :loading="submitting">
        Abrir chamado
      </BaseButton>
    </template>
  </BaseDrawer>
</template>
