<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, KeyRound } from '@lucide/vue'
import AppLayout from '@/components/AppLayout.vue'
import BaseButton from '@/components/BaseButton.vue'
import FormField from '@/components/FormField.vue'
import {
  CUSTOMER_SEGMENTS,
  CUSTOMER_STATUSES,
  DOCUMENT_MASK,
  PHONE_MASK,
  maskAttr,
} from '@/constants/domain'
import { createCustomer, getCustomer, updateCustomer } from '@/utils/api/customers'

const route = useRoute()
const router = useRouter()

const customerId = computed(() => route.params.id ?? null)
const isEditing = computed(() => customerId.value !== null)

const form = ref({
  name: '',
  document: '',
  email: '',
  phone: '',
  contact_name: '',
  segment: 'corban',
  status: 'active',
})

const errors = ref({})
const message = ref('')
const generatedPassword = ref('')
const submitting = ref(false)
const loading = ref(false)

onMounted(async () => {
  if (!isEditing.value) {
    return
  }

  loading.value = true

  try {
    const customer = await getCustomer(customerId.value)

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
})

async function onSubmit() {
  errors.value = {}
  message.value = ''
  generatedPassword.value = ''
  submitting.value = true

  try {
    if (isEditing.value) {
      await updateCustomer(customerId.value, form.value)
      router.push({ name: 'admin.customers.show', params: { id: customerId.value } })

      return
    }

    const { customer, generatedPassword: password } = await createCustomer(form.value)
    generatedPassword.value = password
    message.value = `Cliente "${customer.name}" cadastrado.`
    form.value.name = ''
    form.value.document = ''
    form.value.email = ''
    form.value.phone = ''
    form.value.contact_name = ''
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {}
    message.value = e?.response?.data?.message || 'Não foi possível salvar o cliente.'
  } finally {
    submitting.value = false
  }
}
</script>
<template>
  <AppLayout
    :title="isEditing ? 'Editar cliente' : 'Novo cliente'"
    :subtitle="isEditing ? 'Dados cadastrais e acesso' : 'O cadastro gera o acesso do cliente'"
  >
    <template #actions>
      <router-link :to="{ name: 'admin.customers.index' }">
        <BaseButton type="button" variant="secondary">
          <ArrowLeft class="size-3.5" />
          Voltar
        </BaseButton>
      </router-link>
    </template>
    <div class="max-w-3xl">
      <div v-if="loading" class="panel space-y-4 p-6">
        <div v-for="n in 4" :key="n" class="h-9 animate-pulse rounded-md bg-gray-200" />
      </div>
      <div
        v-if="generatedPassword"
        class="mb-4 flex items-start gap-3 rounded-lg bg-green-100 p-4 ring-1 ring-green-200"
      >
        <KeyRound class="mt-0.5 size-4 shrink-0 text-green-700" />
        <div class="min-w-0">
          <p class="text-sm font-medium text-green-700">{{ message }}</p>
          <p class="mt-1 text-sm text-green-700">
            Senha de acesso gerada:
            <code class="mono ml-1 rounded bg-white px-2 py-0.5 text-[13px] text-gray-900">{{
              generatedPassword
            }}</code>
          </p>
          <p class="mt-1 text-xs text-green-700/80">Anote agora — ela não é exibida novamente.</p>
        </div>
      </div>
      <p
        v-else-if="message"
        class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
      >
        {{ message }}
      </p>
      <form v-if="!loading" class="panel p-6" @submit.prevent="onSubmit">
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Nome / Razão social" name="name" :errors="errors">
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="field"
              :class="errors.name && 'field-error'"
            />
          </FormField>
          <FormField label="CNPJ/CPF" name="document" :errors="errors">
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
          <FormField
            label="E-mail"
            name="email"
            :errors="errors"
            hint="É também a credencial de acesso do cliente."
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
          <FormField label="Nome do contato" name="contact_name" :errors="errors">
            <input
              id="contact_name"
              v-model="form.contact_name"
              type="text"
              class="field"
              :class="errors.contact_name && 'field-error'"
            />
          </FormField>
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
        <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
          <BaseButton type="submit" :disabled="submitting">
            {{ isEditing ? 'Salvar alterações' : 'Cadastrar cliente' }}
          </BaseButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>