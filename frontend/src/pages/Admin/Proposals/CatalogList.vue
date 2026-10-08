<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDrawer from '@/components/BaseDrawer.vue'
import FormField from '@/components/FormField.vue'
import { PROPOSAL_ITEM_TYPES, currencyToCents, formatCurrency } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import {
  createProposalCatalog,
  deleteProposalCatalog,
  listProposalCatalog,
  updateProposalCatalog,
} from '@/utils/api/proposalCatalog'

const confirm = useConfirm()
const toast = useToast()

const items = ref([])
const loading = ref(true)
const error = ref('')
const drawerOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const formErrors = ref({})
const formMessage = ref('')

const blankForm = () => ({
  name: '',
  description: '',
  type: 'recurring',
  default_quantity: 1,
  default_unit_amount: '',
  allows_installments: false,
  is_active: true,
  sort_order: 0,
})

const form = reactive(blankForm())

function open(item = null) {
  editing.value = item?.id || null
  formErrors.value = {}
  formMessage.value = ''
  Object.assign(
    form,
    item
      ? {
          name: item.name,
          description: item.description ?? '',
          type: item.type,
          default_quantity: item.default_quantity,
          default_unit_amount: (item.default_unit_amount_cents / 100).toFixed(2).replace('.', ','),
          allows_installments: Boolean(item.allows_installments),
          is_active: Boolean(item.is_active),
          sort_order: item.sort_order ?? 0,
        }
      : blankForm(),
  )
  drawerOpen.value = true
}

async function load() {
  loading.value = true

  try {
    const result = await listProposalCatalog({ per_page: 100 })
    items.value = result.data || result
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar o catálogo.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  formErrors.value = {}
  formMessage.value = ''

  try {
    const payload = {
      ...form,
      default_quantity: Number(form.default_quantity),
      default_unit_amount_cents: currencyToCents(form.default_unit_amount),
      sort_order: Number(form.sort_order),
    }

    if (editing.value) await updateProposalCatalog(editing.value, payload)
    else await createProposalCatalog(payload)

    toast.success(editing.value ? 'Item atualizado.' : 'Item adicionado ao catálogo.')
    drawerOpen.value = false
    load()
  } catch (e) {
    formErrors.value = e?.response?.data?.errors || {}
    formMessage.value = errorMessage(e, 'Não foi possível salvar o item.')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  const ok = await confirm({
    title: 'Remover do catálogo',
    message: `"${item.name}" deixará de aparecer ao montar propostas. Propostas existentes não são alteradas.`,
    confirmLabel: 'Remover',
    tone: 'danger',
  })

  if (!ok) return

  try {
    await deleteProposalCatalog(item.id)
    toast.success('Item removido.')
    load()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível remover o item.'))
  }
}

onMounted(load)
</script>
<template>
  <AppLayout title="Catálogo de itens" subtitle="Itens padrão usados para montar propostas">
    <template #actions>
      <BaseButton @click="open()">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Novo item</span>
        <span class="sm:hidden">Novo</span>
      </BaseButton>
    </template>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>

    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th">Item</th>
              <th class="th">Tipo</th>
              <th class="th text-right">Qtd. padrão</th>
              <th class="th text-right">Valor padrão</th>
              <th class="th">Status</th>
              <th class="th w-px"><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="n in 4" :key="n">
                <td v-for="col in 6" :key="col" class="td">
                  <div class="h-3.5 animate-pulse rounded bg-gray-200" :class="col === 1 ? 'w-48' : 'w-16'" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!items.length">
              <td colspan="6" class="px-4 py-14 text-center">
                <p class="text-sm font-medium text-gray-900">Catálogo vazio</p>
                <p class="mt-1 text-sm text-gray-500">Cadastre planos e serviços para agilizar as propostas.</p>
                <BaseButton class="mt-4" @click="open()"><Plus class="size-4" /> Novo item</BaseButton>
              </td>
            </tr>
            <tr
              v-for="item in items"
              v-else
              :key="item.id"
              class="group cursor-pointer transition-colors hover:bg-gray-50"
              @click="open(item)"
            >
              <td class="td max-w-md">
                <p class="font-medium group-hover:text-theme-light-700" :class="item.is_active ? 'text-gray-900' : 'text-gray-500'">
                  {{ item.name }}
                </p>
                <p v-if="item.description" class="truncate text-xs text-gray-500">{{ item.description }}</p>
              </td>
              <td class="td whitespace-nowrap text-gray-600">
                {{ item.type_label || item.type }}
                <span v-if="item.allows_installments" class="block text-xs text-gray-400">Permite parcelar</span>
              </td>
              <td class="td text-right tabular-nums text-gray-600">{{ item.default_quantity }}</td>
              <td class="td whitespace-nowrap text-right font-medium tabular-nums">
                {{ formatCurrency(item.default_unit_amount_cents) }}
              </td>
              <td class="td">
                <BadgeTag :label="item.is_active ? 'Ativo' : 'Inativo'" :tone="item.is_active ? 'active' : 'inactive'" />
              </td>
              <td class="td whitespace-nowrap" @click.stop>
                <div class="flex justify-end gap-0.5">
                  <BaseButton variant="ghost" size="sm" icon label="Editar" @click="open(item)">
                    <Pencil class="size-4" />
                  </BaseButton>
                  <BaseButton variant="ghost-danger" size="sm" icon label="Remover" @click="remove(item)">
                    <Trash2 class="size-4" />
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <BaseDrawer
      :open="drawerOpen"
      :title="editing ? 'Editar item do catálogo' : 'Novo item do catálogo'"
      description="Valores padrão sugeridos ao adicionar o item numa proposta"
      @close="drawerOpen = false"
    >
      <form id="catalog-form" class="space-y-4" @submit.prevent="save">
        <AlertBanner v-if="formMessage">{{ formMessage }}</AlertBanner>
        <FormField label="Nome" name="name" :errors="formErrors" required>
          <input id="name" v-model="form.name" required class="field" :class="formErrors.name && 'field-error'" />
        </FormField>
        <FormField label="Descrição" name="description" :errors="formErrors">
          <textarea id="description" v-model="form.description" rows="3" class="field" />
        </FormField>
        <FormField label="Tipo" name="type" :errors="formErrors">
          <div class="grid grid-cols-2 gap-1 rounded-md bg-gray-100 p-1" role="radiogroup">
            <button
              v-for="type in PROPOSAL_ITEM_TYPES"
              :key="type.value"
              type="button"
              role="radio"
              :aria-checked="form.type === type.value"
              class="h-7 rounded text-[13px] font-medium transition-colors"
              :class="form.type === type.value ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
              @click="form.type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
        </FormField>
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Quantidade padrão" name="default_quantity" :errors="formErrors">
            <input id="default_quantity" v-model="form.default_quantity" type="number" min="1" class="field" />
          </FormField>
          <FormField label="Valor padrão (R$)" name="default_unit_amount_cents" :errors="formErrors">
            <input
              id="default_unit_amount_cents"
              v-model="form.default_unit_amount"
              inputmode="decimal"
              class="field text-right tabular-nums"
              placeholder="0,00"
            />
          </FormField>
        </div>
        <div class="space-y-3 rounded-lg border border-gray-200 p-4">
          <label class="flex items-start gap-3">
            <input v-model="form.allows_installments" type="checkbox" class="checkbox mt-0.5" />
            <span>
              <span class="block text-sm font-medium text-gray-900">Permite parcelamento</span>
              <span class="block text-xs text-gray-500">Para itens pontuais, como implantação.</span>
            </span>
          </label>
          <label class="flex items-start gap-3">
            <input v-model="form.is_active" type="checkbox" class="checkbox mt-0.5" />
            <span>
              <span class="block text-sm font-medium text-gray-900">Ativo</span>
              <span class="block text-xs text-gray-500">Itens inativos não aparecem ao montar propostas.</span>
            </span>
          </label>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="drawerOpen = false">Cancelar</BaseButton>
        <BaseButton type="submit" form="catalog-form" :loading="saving">
          {{ editing ? 'Salvar alterações' : 'Adicionar ao catálogo' }}
        </BaseButton>
      </template>
    </BaseDrawer>
  </AppLayout>
</template>
