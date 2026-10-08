<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDrawer from '@/components/BaseDrawer.vue'
import FormField from '@/components/FormField.vue'
import { PROPOSAL_TEMPLATE_TYPES } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import {
  createProposalTemplate,
  deleteProposalTemplate,
  listProposalTemplates,
  updateProposalTemplate,
} from '@/utils/api/proposalTemplates'

const confirm = useConfirm()
const toast = useToast()

const templates = ref([])
const loading = ref(true)
const error = ref('')
const drawerOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const formErrors = ref({})
const formMessage = ref('')
const form = reactive({ name: '', type: 'observations', content: '' })

const groups = computed(() =>
  PROPOSAL_TEMPLATE_TYPES.map((type) => ({
    ...type,
    items: templates.value.filter((item) => item.type === type.value),
  })),
)

function open(item = null, type = 'observations') {
  editing.value = item?.id || null
  formErrors.value = {}
  formMessage.value = ''
  Object.assign(
    form,
    item ? { name: item.name, type: item.type, content: item.content } : { name: '', type, content: '' },
  )
  drawerOpen.value = true
}

async function load() {
  loading.value = true

  try {
    const result = await listProposalTemplates({ per_page: 100 })
    templates.value = result.data || result
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar os modelos.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  formErrors.value = {}
  formMessage.value = ''

  try {
    if (editing.value) await updateProposalTemplate(editing.value, form)
    else await createProposalTemplate(form)

    toast.success(editing.value ? 'Modelo atualizado.' : 'Modelo criado.')
    drawerOpen.value = false
    load()
  } catch (e) {
    formErrors.value = e?.response?.data?.errors || {}
    formMessage.value = errorMessage(e, 'Não foi possível salvar o modelo.')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  const ok = await confirm({
    title: 'Remover modelo',
    message: `"${item.name}" será removido. Textos já copiados para propostas não são afetados.`,
    confirmLabel: 'Remover',
    tone: 'danger',
  })

  if (!ok) return

  try {
    await deleteProposalTemplate(item.id)
    toast.success('Modelo removido.')
    load()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível remover o modelo.'))
  }
}

onMounted(load)
</script>
<template>
  <AppLayout title="Modelos de texto" subtitle="Textos reutilizáveis para observações e condições gerais">
    <template #actions>
      <BaseButton @click="open()">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Novo modelo</span>
        <span class="sm:hidden">Novo</span>
      </BaseButton>
    </template>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>

    <div v-if="loading" class="grid gap-4 lg:grid-cols-2">
      <div v-for="n in 2" :key="n" class="panel h-56 animate-pulse bg-gray-100" />
    </div>

    <div v-else class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
      <section v-for="group in groups" :key="group.value" class="panel overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900">
            {{ group.label }}
            <span class="ml-1 text-xs font-normal tabular-nums text-gray-500">{{ group.items.length }}</span>
          </h2>
          <BaseButton variant="ghost" size="sm" @click="open(null, group.value)">
            <Plus class="size-3.5" /> Adicionar
          </BaseButton>
        </div>
        <p v-if="!group.items.length" class="px-5 py-10 text-center text-sm text-gray-500">
          Nenhum modelo cadastrado.
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="item in group.items" :key="item.id" class="group flex items-start gap-3 px-5 py-4 hover:bg-gray-50">
            <button type="button" class="min-w-0 flex-1 text-left" @click="open(item)">
              <h3 class="text-sm font-medium text-gray-900 group-hover:text-theme-light-700">{{ item.name }}</h3>
              <p class="mt-1 line-clamp-3 whitespace-pre-wrap text-[13px] leading-relaxed text-gray-600">
                {{ item.content }}
              </p>
            </button>
            <div class="flex shrink-0 gap-0.5">
              <BaseButton variant="ghost" size="sm" icon label="Editar" @click="open(item)">
                <Pencil class="size-4" />
              </BaseButton>
              <BaseButton variant="ghost-danger" size="sm" icon label="Remover" @click="remove(item)">
                <Trash2 class="size-4" />
              </BaseButton>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <BaseDrawer
      :open="drawerOpen"
      :title="editing ? 'Editar modelo' : 'Novo modelo'"
      description="O texto é copiado para a proposta ao ser inserido"
      size="lg"
      @close="drawerOpen = false"
    >
      <form id="template-form" class="space-y-4" @submit.prevent="save">
        <AlertBanner v-if="formMessage">{{ formMessage }}</AlertBanner>
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Nome" name="name" :errors="formErrors" required>
            <input id="name" v-model="form.name" required class="field" :class="formErrors.name && 'field-error'" />
          </FormField>
          <FormField label="Tipo" name="type" :errors="formErrors">
            <select id="type" v-model="form.type" class="field">
              <option v-for="type in PROPOSAL_TEMPLATE_TYPES" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </FormField>
        </div>
        <FormField label="Conteúdo" name="content" :errors="formErrors" required>
          <textarea
            id="content"
            v-model="form.content"
            rows="16"
            required
            class="field leading-relaxed"
            :class="formErrors.content && 'field-error'"
          />
        </FormField>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="drawerOpen = false">Cancelar</BaseButton>
        <BaseButton type="submit" form="template-form" :loading="saving">
          {{ editing ? 'Salvar alterações' : 'Criar modelo' }}
        </BaseButton>
      </template>
    </BaseDrawer>
  </AppLayout>
</template>
