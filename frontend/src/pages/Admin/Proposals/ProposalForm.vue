<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Lock, Pencil, Plus, Save, Trash2 } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseModal from '@/components/BaseModal.vue'
import FormField from '@/components/FormField.vue'
import {
  PAYMENT_METHODS,
  PROPOSAL_ITEM_TYPES,
  currencyToCents,
  formatCurrency,
} from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { listProposalCatalog } from '@/utils/api/proposalCatalog'
import { listProposalTemplates } from '@/utils/api/proposalTemplates'
import {
  addProposalItem,
  createProposal,
  getProposal,
  removeProposalItem,
  updateProposal,
  updateProposalItem,
} from '@/utils/api/proposals'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const id = computed(() => route.params.id || null)
const editing = computed(() => Boolean(id.value))
const proposal = ref(null)
const customers = ref([])
const catalog = ref([])
const templates = ref([])
const errors = ref({})
const message = ref('')
const loading = ref(false)
const saving = ref(false)
const itemSaving = ref(false)
const itemErrors = ref({})
const itemMessage = ref('')
const itemEditor = ref(null)
const itemModalOpen = ref(false)

const readonly = computed(() => Boolean(proposal.value && !proposal.value.is_editable))

function localToday() {
  const now = new Date()
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset())

  return now.toISOString().slice(0, 10)
}

function addDays(dateString, days) {
  const date = new Date(`${dateString}T12:00:00`)
  date.setDate(date.getDate() + days)

  return date.toISOString().slice(0, 10)
}

const form = reactive({
  customer_id: route.query.customer_id ? Number(route.query.customer_id) : '',
  title: '',
  issued_on: localToday(),
  valid_until: addDays(localToday(), 15),
  payment_method: '',
  payment_notes: '',
  observations: '',
  terms: '',
})

const blankItem = () => ({
  type: 'one_time',
  description: '',
  quantity: 1,
  unit_amount: '',
  discount: '',
  installments: 1,
  catalog_item_id: null,
})

const observationTemplates = computed(() => templates.value.filter((t) => t.type === 'observations'))
const termsTemplates = computed(() => templates.value.filter((t) => t.type === 'general_conditions'))

const itemPreview = computed(() => {
  if (!itemEditor.value) return 0

  const total =
    Number(itemEditor.value.quantity || 0) * currencyToCents(itemEditor.value.unit_amount) -
    currencyToCents(itemEditor.value.discount)

  return Math.max(0, total)
})

const itemInstallments = computed(() =>
  itemEditor.value?.type === 'one_time' ? Math.max(1, Number(itemEditor.value.installments) || 1) : 1,
)

function setForm(value) {
  Object.assign(form, {
    customer_id: value.customer_id,
    title: value.title,
    issued_on: value.issued_on,
    valid_until: value.valid_until,
    payment_method: value.payment_method || '',
    payment_notes: value.payment_notes || '',
    observations: value.observations || '',
    terms: value.terms || '',
  })
}

function payload() {
  return { ...form, customer_id: Number(form.customer_id) }
}

async function load() {
  loading.value = true

  try {
    const [customerResponse, catalogResponse, templateResponse] = await Promise.all([
      listCustomers({ per_page: 100, status: 'active' }),
      listProposalCatalog({ per_page: 100, active: 1 }),
      listProposalTemplates({ per_page: 100 }),
    ])

    customers.value = customerResponse.data
    catalog.value = catalogResponse.data || catalogResponse
    templates.value = templateResponse.data || templateResponse

    if (editing.value) {
      proposal.value = await getProposal(id.value)
      setForm(proposal.value)
    }
  } catch (e) {
    message.value = errorMessage(e, 'Não foi possível carregar os dados.')
  } finally {
    loading.value = false
  }
}

async function onSubmit() {
  errors.value = {}
  message.value = ''
  saving.value = true

  try {
    if (editing.value) {
      await updateProposal(id.value, payload())
      toast.success('Proposta salva.')
      router.push({ name: 'admin.proposals.show', params: { id: id.value } })

      return
    }

    const result = await createProposal(payload())
    proposal.value = result
    toast.success('Proposta criada. Agora adicione os itens.')
    router.replace({ name: 'admin.proposals.edit', params: { id: result.id } })
  } catch (e) {
    errors.value = e?.response?.data?.errors || {}
    message.value = errorMessage(e, 'Não foi possível salvar a proposta.')
  } finally {
    saving.value = false
  }
}

function centsToInput(cents) {
  return cents ? (cents / 100).toFixed(2).replace('.', ',') : ''
}

function editItem(item = null) {
  itemErrors.value = {}
  itemMessage.value = ''
  itemEditor.value = item
    ? {
        ...item,
        unit_amount: centsToInput(item.unit_amount_cents),
        discount: centsToInput(item.discount_cents),
      }
    : blankItem()
  itemModalOpen.value = true
}

function applyCatalog(value) {
  const selected = catalog.value.find((item) => String(item.id) === String(value))

  if (!selected) {
    itemEditor.value.catalog_item_id = null

    return
  }

  Object.assign(itemEditor.value, {
    catalog_item_id: selected.id,
    type: selected.type,
    description: selected.name,
    quantity: selected.default_quantity,
    unit_amount: centsToInput(selected.default_unit_amount_cents),
    installments: 1,
  })
}

function itemPayload() {
  return {
    catalog_item_id: itemEditor.value.catalog_item_id,
    type: itemEditor.value.type,
    description: itemEditor.value.description,
    quantity: Number(itemEditor.value.quantity),
    unit_amount_cents: currencyToCents(itemEditor.value.unit_amount),
    discount_cents: currencyToCents(itemEditor.value.discount),
    installments: itemInstallments.value,
  }
}

async function saveItem() {
  itemErrors.value = {}
  itemMessage.value = ''
  itemSaving.value = true

  try {
    proposal.value = itemEditor.value.id
      ? await updateProposalItem(proposal.value.id, itemEditor.value.id, itemPayload())
      : await addProposalItem(proposal.value.id, itemPayload())
    toast.success(itemEditor.value.id ? 'Item atualizado.' : 'Item adicionado.')
    itemModalOpen.value = false
  } catch (e) {
    itemErrors.value = e?.response?.data?.errors || {}
    itemMessage.value = errorMessage(e, 'Não foi possível salvar o item.')
  } finally {
    itemSaving.value = false
  }
}

async function removeItem(item) {
  const ok = await confirm({
    title: 'Remover item',
    message: `"${item.description}" será removido da proposta.`,
    confirmLabel: 'Remover',
    tone: 'danger',
  })

  if (!ok) return

  try {
    proposal.value = await removeProposalItem(proposal.value.id, item.id)
    toast.success('Item removido.')
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível remover o item.'))
  }
}

async function applyTemplate(field, event) {
  const selected = templates.value.find((template) => String(template.id) === event.target.value)
  event.target.value = ''

  if (!selected) return

  if (form[field]?.trim()) {
    const ok = await confirm({
      title: 'Substituir texto',
      message: `O texto atual será substituído pelo modelo "${selected.name}".`,
      confirmLabel: 'Substituir',
    })

    if (!ok) return
  }

  form[field] = selected.content
}

onMounted(load)
</script>
<template>
  <AppLayout
    :title="editing ? 'Editar proposta' : 'Nova proposta'"
    :subtitle="proposal ? `${proposal.reference} · ${proposal.customer?.name ?? ''}` : 'Dados comerciais e itens da proposta'"
    :back="editing ? { name: 'admin.proposals.show', params: { id } } : { name: 'admin.proposals.index' }"
  >
    <template #actions>
      <BaseButton type="submit" form="proposal-form" :loading="saving" :disabled="loading || readonly">
        <Save class="size-4" />
        <span class="hidden sm:inline">{{ editing ? 'Salvar proposta' : 'Criar proposta' }}</span>
        <span class="sm:hidden">Salvar</span>
      </BaseButton>
    </template>

    <div v-if="loading" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="panel h-96 animate-pulse bg-gray-100 lg:col-span-2" />
      <div class="panel h-48 animate-pulse bg-gray-100" />
    </div>

    <template v-else>
      <AlertBanner v-if="message" class="mb-4" dismissible @dismiss="message = ''">{{ message }}</AlertBanner>
      <AlertBanner v-if="readonly" tone="warning" class="mb-4">
        Somente propostas em rascunho podem ser editadas.
      </AlertBanner>

      <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <form id="proposal-form" class="panel" @submit.prevent="onSubmit">
            <h2 class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-900">Dados da proposta</h2>
            <fieldset :disabled="readonly" class="grid min-w-0 gap-4 p-5 sm:grid-cols-2">
              <FormField label="Cliente" name="customer_id" :errors="errors" required>
                <select
                  id="customer_id"
                  v-model="form.customer_id"
                  class="field"
                  :class="errors.customer_id && 'field-error'"
                  required
                >
                  <option value="" disabled>Selecione…</option>
                  <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                    {{ customer.name }}
                  </option>
                </select>
              </FormField>
              <FormField label="Título" name="title" :errors="errors" required>
                <input
                  id="title"
                  v-model="form.title"
                  class="field"
                  :class="errors.title && 'field-error'"
                  placeholder="Ex.: Plano Corban Pro"
                  required
                />
              </FormField>
              <FormField label="Data de emissão" name="issued_on" :errors="errors" required>
                <input id="issued_on" v-model="form.issued_on" type="date" class="field" required />
              </FormField>
              <FormField label="Validade" name="valid_until" :errors="errors" required>
                <input
                  id="valid_until"
                  v-model="form.valid_until"
                  type="date"
                  class="field"
                  :min="form.issued_on"
                  :class="errors.valid_until && 'field-error'"
                  required
                />
              </FormField>
              <FormField label="Forma de pagamento" name="payment_method" :errors="errors">
                <select id="payment_method" v-model="form.payment_method" class="field">
                  <option value="">Não definida</option>
                  <option v-for="method in PAYMENT_METHODS" :key="method.value" :value="method.value">
                    {{ method.label }}
                  </option>
                </select>
              </FormField>
              <FormField label="Observações de pagamento" name="payment_notes" :errors="errors">
                <input
                  id="payment_notes"
                  v-model="form.payment_notes"
                  class="field"
                  placeholder="Ex.: vencimento todo dia 10"
                />
              </FormField>
              <div>
                <div class="mb-1.5 flex items-center justify-between gap-2">
                  <label class="text-[13px] font-medium text-gray-700" for="observations">Observações</label>
                  <select
                    v-if="observationTemplates.length"
                    class="h-7 rounded border-0 bg-transparent pr-6 text-xs font-medium text-theme-light-700 hover:bg-gray-50 focus:ring-2 focus:ring-theme-light-500/30"
                    aria-label="Inserir modelo de observações"
                    @change="applyTemplate('observations', $event)"
                  >
                    <option value="">Inserir modelo…</option>
                    <option v-for="template in observationTemplates" :key="template.id" :value="template.id">
                      {{ template.name }}
                    </option>
                  </select>
                </div>
                <textarea id="observations" v-model="form.observations" rows="5" class="field" />
              </div>
              <div>
                <div class="mb-1.5 flex items-center justify-between gap-2">
                  <label class="text-[13px] font-medium text-gray-700" for="terms">Condições gerais</label>
                  <select
                    v-if="termsTemplates.length"
                    class="h-7 rounded border-0 bg-transparent pr-6 text-xs font-medium text-theme-light-700 hover:bg-gray-50 focus:ring-2 focus:ring-theme-light-500/30"
                    aria-label="Inserir modelo de condições gerais"
                    @change="applyTemplate('terms', $event)"
                  >
                    <option value="">Inserir modelo…</option>
                    <option v-for="template in termsTemplates" :key="template.id" :value="template.id">
                      {{ template.name }}
                    </option>
                  </select>
                </div>
                <textarea id="terms" v-model="form.terms" rows="5" class="field" />
              </div>
            </fieldset>
          </form>

          <section class="panel overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 class="text-sm font-semibold text-gray-900">
                Itens
                <span v-if="proposal" class="ml-1 text-xs font-normal tabular-nums text-gray-500">
                  {{ proposal.items?.length ?? 0 }}
                </span>
              </h2>
              <BaseButton v-if="proposal" size="sm" :disabled="readonly" @click="editItem()">
                <Plus class="size-3.5" /> Adicionar item
              </BaseButton>
            </div>
            <div v-if="!proposal" class="flex flex-col items-center px-5 py-10 text-center">
              <div class="flex size-10 items-center justify-center rounded-full bg-gray-100">
                <Lock class="size-4 text-gray-500" />
              </div>
              <p class="mt-3 text-sm font-medium text-gray-900">Crie a proposta para adicionar itens</p>
              <p class="mt-1 max-w-sm text-sm text-gray-500">
                Preencha os dados acima e clique em “Criar proposta”. Você continua nesta tela para montar os itens.
              </p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="th">Descrição</th>
                    <th class="th">Tipo</th>
                    <th class="th text-right">Qtd.</th>
                    <th class="th text-right">Total</th>
                    <th class="th w-px"><span class="sr-only">Ações</span></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="item in proposal.items" :key="item.id" class="hover:bg-gray-50">
                    <td class="td">
                      <button
                        type="button"
                        class="text-left font-medium text-gray-900 hover:text-theme-light-700 disabled:pointer-events-none"
                        :disabled="readonly"
                        @click="editItem(item)"
                      >
                        {{ item.description }}
                      </button>
                      <p v-if="item.discount_cents" class="text-xs text-gray-500">
                        Desconto de {{ formatCurrency(item.discount_cents) }}
                      </p>
                    </td>
                    <td class="td whitespace-nowrap text-gray-600">{{ item.type_label }}</td>
                    <td class="td text-right tabular-nums">{{ item.quantity }}</td>
                    <td class="td whitespace-nowrap text-right tabular-nums">
                      <span class="font-medium">{{ formatCurrency(item.line_total_cents) }}</span>
                      <span v-if="item.installments > 1" class="block text-xs text-gray-500">
                        {{ item.installments }}x de {{ formatCurrency(item.installment_amount_cents) }}
                      </span>
                    </td>
                    <td class="td whitespace-nowrap">
                      <div class="flex justify-end gap-0.5">
                        <BaseButton variant="ghost" size="sm" icon label="Editar item" :disabled="readonly" @click="editItem(item)">
                          <Pencil class="size-4" />
                        </BaseButton>
                        <BaseButton
                          variant="ghost-danger"
                          size="sm"
                          icon
                          label="Remover item"
                          :disabled="readonly"
                          @click="removeItem(item)"
                        >
                          <Trash2 class="size-4" />
                        </BaseButton>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!proposal.items?.length">
                    <td colspan="5" class="px-4 py-10 text-center">
                      <p class="text-sm text-gray-500">Nenhum item adicionado.</p>
                      <BaseButton variant="secondary" size="sm" class="mt-3" :disabled="readonly" @click="editItem()">
                        <Plus class="size-3.5" /> Adicionar o primeiro item
                      </BaseButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <aside class="panel divide-y divide-gray-100 lg:sticky lg:top-20">
          <div class="p-5">
            <p class="stat-label">Total</p>
            <p class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">
              {{ formatCurrency(proposal?.totals?.total_cents) }}
            </p>
          </div>
          <div class="grid grid-cols-2 gap-3 p-5">
            <div>
              <p class="stat-label">Recorrente mensal</p>
              <p class="mt-0.5 font-semibold tabular-nums">{{ formatCurrency(proposal?.totals?.mrr_cents) }}</p>
            </div>
            <div>
              <p class="stat-label">Primeiro pagamento</p>
              <p class="mt-0.5 font-semibold tabular-nums">
                {{ formatCurrency(proposal?.totals?.first_payment_cents) }}
              </p>
            </div>
            <div v-if="proposal?.totals?.max_installments > 1" class="col-span-2">
              <p class="stat-label">Durante as parcelas</p>
              <p class="mt-0.5 font-semibold tabular-nums">
                {{ formatCurrency(proposal.totals.during_installments_cents) }}
              </p>
            </div>
          </div>
          <div class="p-5">
            <BaseButton type="submit" form="proposal-form" block :loading="saving" :disabled="readonly">
              <Save class="size-4" />
              {{ editing ? 'Salvar proposta' : 'Criar proposta' }}
            </BaseButton>
          </div>
        </aside>
      </div>
    </template>

    <BaseModal
      :open="itemModalOpen"
      :title="itemEditor?.id ? 'Editar item' : 'Adicionar item'"
      description="Preencha a partir do catálogo ou informe os valores manualmente."
      size="lg"
      @close="itemModalOpen = false"
    >
      <form v-if="itemEditor" id="item-form" class="space-y-4" @submit.prevent="saveItem">
        <AlertBanner v-if="itemMessage">{{ itemMessage }}</AlertBanner>
        <div v-if="catalog.length && !itemEditor.id">
          <label class="field-label" for="item-catalog">Catálogo</label>
          <select
            id="item-catalog"
            class="field"
            :value="itemEditor.catalog_item_id ?? ''"
            @change="applyCatalog($event.target.value)"
          >
            <option value="">Item avulso (preencher manualmente)</option>
            <option v-for="item in catalog" :key="item.id" :value="item.id">
              {{ item.name }} — {{ formatCurrency(item.default_unit_amount_cents) }}
            </option>
          </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <FormField label="Descrição" name="description" :errors="itemErrors" required>
              <input id="description" v-model="itemEditor.description" class="field" required />
            </FormField>
          </div>
          <FormField label="Tipo" name="type" :errors="itemErrors">
            <select id="type" v-model="itemEditor.type" class="field">
              <option v-for="type in PROPOSAL_ITEM_TYPES" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </FormField>
          <FormField label="Quantidade" name="quantity" :errors="itemErrors" required>
            <input id="quantity" v-model="itemEditor.quantity" type="number" min="1" class="field" required />
          </FormField>
          <FormField label="Valor unitário (R$)" name="unit_amount_cents" :errors="itemErrors" required>
            <input
              id="unit_amount_cents"
              v-model="itemEditor.unit_amount"
              inputmode="decimal"
              class="field text-right tabular-nums"
              placeholder="0,00"
              required
            />
          </FormField>
          <FormField label="Desconto (R$)" name="discount_cents" :errors="itemErrors">
            <input
              id="discount_cents"
              v-model="itemEditor.discount"
              inputmode="decimal"
              class="field text-right tabular-nums"
              placeholder="0,00"
            />
          </FormField>
          <FormField
            v-if="itemEditor.type === 'one_time'"
            label="Parcelas"
            name="installments"
            :errors="itemErrors"
            hint="Até 12x"
          >
            <input id="installments" v-model="itemEditor.installments" type="number" min="1" max="12" class="field" />
          </FormField>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 ring-1 ring-gray-200">
          <span class="text-[13px] text-gray-600">Total do item</span>
          <span class="text-right">
            <span class="block text-base font-semibold tabular-nums text-gray-900">
              {{ formatCurrency(itemPreview) }}<span v-if="itemEditor.type === 'recurring'" class="text-xs font-normal text-gray-500">/mês</span>
            </span>
            <span v-if="itemInstallments > 1" class="text-xs tabular-nums text-gray-500">
              {{ itemInstallments }}x de {{ formatCurrency(Math.floor(itemPreview / itemInstallments)) }}
            </span>
          </span>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="itemModalOpen = false">Cancelar</BaseButton>
        <BaseButton type="submit" form="item-form" :loading="itemSaving">
          {{ itemEditor?.id ? 'Salvar item' : 'Adicionar item' }}
        </BaseButton>
      </template>
    </BaseModal>
  </AppLayout>
</template>
