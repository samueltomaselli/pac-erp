<script setup>
import { computed, ref, watch } from 'vue'
import { Check, Copy, KeyRound } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDrawer from '@/components/BaseDrawer.vue'
import FormField from '@/components/FormField.vue'
import {
  CUSTOMER_SEGMENTS,
  CUSTOMER_STATUSES,
  DOCUMENT_MASK,
  PHONE_MASK,
  maskAttr,
} from '@/constants/domain'
import { useToast } from '@/composables/useToast'
import { createCustomer, getCustomer, updateCustomer } from '@/utils/api/customers'

const props = defineProps({
  open: { type: Boolean, default: false },
  customerId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const blankForm = () => ({
  name: '',
  document: '',
  email: '',
  phone: '',
  contact_name: '',
  segment: 'corban',
  status: 'active',
})

const form = ref(blankForm())
const errors = ref({})
const message = ref('')
const submitting = ref(false)
const loading = ref(false)
const created = ref(null)
const copied = ref(false)

const isEditing = computed(() => props.customerId !== null && props.customerId !== undefined)

watch(
  () => props.open,
  async (open) => {
    if (!open) {
      return
    }

    errors.value = {}
    message.value = ''
    created.value = null
    copied.value = false
    form.value = blankForm()

    if (!isEditing.value) {
      return
    }

    loading.value = true

    try {
      const customer = await getCustomer(props.customerId)

      form.value = {
        name: customer.name,
        document: customer.document_formatted,
        email: customer.email,
        phone: customer.phone ?? '',
        contact_name: customer.contact_name ?? '',
        segment: customer.segment,
        status: customer.status,
      }
    } catch {
      message.value = 'Não foi possível carregar o cliente.'
    } finally {
      loading.value = false
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
      const customer = await updateCustomer(props.customerId, form.value)
      toast.success('Cliente atualizado.')
      emit('saved', customer)
      emit('close')

      return
    }

    const { customer, generatedPassword } = await createCustomer(form.value)
    created.value = { customer, password: generatedPassword }
    emit('saved', customer)
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {}
    message.value = e?.response?.data?.message || 'Não foi possível salvar o cliente.'
  } finally {
    submitting.value = false
  }
}

async function copyPassword() {
  try {
    await navigator.clipboard.writeText(created.value.password)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    toast.error('Não foi possível copiar. Selecione a senha e copie manualmente.')
  }
}

function startAnother() {
  created.value = null
  form.value = blankForm()
}
</script>
<template>
  <BaseDrawer
    :open="open"
    :title="created ? 'Cliente cadastrado' : isEditing ? 'Editar cliente' : 'Novo cliente'"
    :description="created ? '' : isEditing ? 'Dados cadastrais e acesso' : 'O cadastro gera o acesso do cliente'"
    @close="emit('close')"
  >
    <div v-if="created" class="space-y-4">
      <AlertBanner tone="success">
        <p class="font-medium">"{{ created.customer.name }}" foi cadastrado.</p>
      </AlertBanner>
      <div class="rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-2 text-sm font-medium text-gray-900">
          <KeyRound class="size-4 text-gray-500" />
          Senha de acesso gerada
        </div>
        <div class="mt-3 flex items-center gap-2">
          <code class="mono flex-1 select-all rounded-md bg-gray-100 px-3 py-2 text-[15px] text-gray-900">
            {{ created.password }}
          </code>
          <BaseButton variant="secondary" @click="copyPassword">
            <component :is="copied ? Check : Copy" class="size-4" />
            {{ copied ? 'Copiada' : 'Copiar' }}
          </BaseButton>
        </div>
        <p class="mt-2 text-xs text-amber-700">Anote agora — ela não é exibida novamente.</p>
      </div>
    </div>
    <div v-else-if="loading" class="space-y-4">
      <div v-for="n in 6" :key="n" class="space-y-2">
        <div class="h-3 w-24 animate-pulse rounded bg-gray-200" />
        <div class="h-9 animate-pulse rounded-md bg-gray-100" />
      </div>
    </div>
    <form v-else id="customer-form" class="space-y-4" @submit.prevent="onSubmit">
      <AlertBanner v-if="message">{{ message }}</AlertBanner>
      <FormField label="Nome / Razão social" name="name" :errors="errors" required>
        <input
          id="name"
          v-model="form.name"
          type="text"
          required
          class="field"
          :class="errors.name && 'field-error'"
        />
      </FormField>
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="CNPJ/CPF" name="document" :errors="errors" required>
          <input
            id="document"
            v-model="form.document"
            v-maska
            :data-maska="maskAttr(DOCUMENT_MASK)"
            type="text"
            inputmode="numeric"
            required
            placeholder="000.000.000-00"
            class="field mono"
            :class="errors.document && 'field-error'"
          />
        </FormField>
        <FormField label="Telefone" name="phone" :errors="errors">
          <input
            id="phone"
            v-model="form.phone"
            v-maska
            :data-maska="maskAttr(PHONE_MASK)"
            type="tel"
            inputmode="numeric"
            placeholder="(00) 00000-0000"
            class="field"
            :class="errors.phone && 'field-error'"
          />
        </FormField>
      </div>
      <FormField
        label="E-mail"
        name="email"
        :errors="errors"
        hint="É também a credencial de acesso do cliente."
        required
      >
        <input
          id="email"
          v-model="form.email"
          type="email"
          required
          class="field"
          :class="errors.email && 'field-error'"
        />
      </FormField>
      <FormField label="Nome do contato" name="contact_name" :errors="errors">
        <input
          id="contact_name"
          v-model="form.contact_name"
          type="text"
          class="field"
          :class="errors.contact_name && 'field-error'"
        />
      </FormField>
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Segmento" name="segment" :errors="errors">
          <select id="segment" v-model="form.segment" class="field">
            <option v-for="option in CUSTOMER_SEGMENTS" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </FormField>
        <FormField label="Status" name="status" :errors="errors">
          <select id="status" v-model="form.status" class="field">
            <option v-for="option in CUSTOMER_STATUSES" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </FormField>
      </div>
    </form>
    <template #footer>
      <template v-if="created">
        <BaseButton variant="secondary" @click="startAnother">Cadastrar outro</BaseButton>
        <BaseButton
          :to="{ name: 'admin.customers.show', params: { id: created.customer.id } }"
          @click="emit('close')"
        >
          Abrir cliente
        </BaseButton>
      </template>
      <template v-else>
        <BaseButton variant="secondary" @click="emit('close')">Cancelar</BaseButton>
        <BaseButton type="submit" form="customer-form" :loading="submitting" :disabled="loading">
          {{ isEditing ? 'Salvar alterações' : 'Cadastrar cliente' }}
        </BaseButton>
      </template>
    </template>
  </BaseDrawer>
</template>
