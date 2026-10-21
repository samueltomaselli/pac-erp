<script setup>
import { ref, watch } from 'vue'
import { Download, Paperclip, Send } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BaseButton from '@/components/BaseButton.vue'
import TicketAttachmentPicker from '@/components/TicketAttachmentPicker.vue'
import { formatDateTime } from '@/constants/domain'
import { errorMessage, useToast } from '@/composables/useToast'
import {
  createCustomerTicketComment,
  downloadCustomerTicketAttachment,
} from '@/utils/api/customerTickets'
import {
  createTicketComment,
  downloadTicketAttachment,
} from '@/utils/api/tickets'

const props = defineProps({
  ticket: { type: Object, required: true },
  customerMode: { type: Boolean, default: false },
})

const emit = defineEmits(['updated'])
const toast = useToast()
const body = ref('')
const attachments = ref([])
const submitting = ref(false)
const error = ref('')

watch(
  () => props.ticket.id,
  () => {
    body.value = ''
    attachments.value = []
    error.value = ''
  },
)

async function sendComment() {
  if (!body.value.trim()) {
    error.value = 'Escreva uma mensagem antes de enviar.'
    return
  }

  submitting.value = true
  error.value = ''

  try {
    if (props.customerMode) {
      await createCustomerTicketComment(props.ticket.id, body.value.trim(), attachments.value)
    } else {
      await createTicketComment(props.ticket.id, body.value.trim(), attachments.value)
    }

    body.value = ''
    attachments.value = []
    toast.success('Comentário enviado.')
    emit('updated')
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível enviar o comentário.')
  } finally {
    submitting.value = false
  }
}

async function download(attachment) {
  try {
    const response = props.customerMode
      ? await downloadCustomerTicketAttachment(attachment.id)
      : await downloadTicketAttachment(attachment.id)
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.original_name
    link.style.display = 'none'
    document.body.append(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível baixar o anexo.'))
  }
}
</script>

<template>
  <section class="panel overflow-hidden">
    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3">
      <h2 class="text-sm font-semibold text-gray-900">Conversa</h2>
    </header>
    <div v-if="ticket.comments?.length" class="divide-y divide-gray-100">
      <article v-for="comment in ticket.comments" :key="comment.id" class="px-5 py-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
          <p class="text-[13px] font-semibold text-gray-900">
            {{ comment.author?.name || 'Usuário' }}
            <span class="ml-1 font-normal text-gray-500">
              {{ comment.author?.role === 'admin' ? '· Equipe' : '· Cliente' }}
            </span>
          </p>
          <time class="text-xs text-gray-500">{{ formatDateTime(comment.created_at) }}</time>
        </div>
        <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-700">{{ comment.body }}</p>
        <div v-if="comment.attachments?.length" class="mt-3 space-y-1.5">
          <button
            v-for="attachment in comment.attachments"
            :key="attachment.id"
            type="button"
            class="inline-flex max-w-full items-center gap-1.5 text-[13px] text-theme-light-700 hover:underline"
            @click="download(attachment)"
          >
            <Paperclip class="size-3.5 shrink-0" />
            <span class="truncate">{{ attachment.original_name }}</span>
            <Download class="size-3.5 shrink-0" />
          </button>
        </div>
      </article>
    </div>
    <p v-else class="px-5 py-8 text-center text-[13px] text-gray-500">Nenhum comentário ainda.</p>

    <div class="border-t border-gray-200 p-5">
      <AlertBanner v-if="ticket.status === 'closed'" tone="info">
        Este chamado está fechado e não aceita novos comentários.
      </AlertBanner>
      <form v-else class="space-y-3" @submit.prevent="sendComment">
        <AlertBanner v-if="error">{{ error }}</AlertBanner>
        <label for="ticket-comment" class="field-label">Novo comentário</label>
        <textarea
          id="ticket-comment"
          v-model="body"
          rows="4"
          class="field !h-auto !text-[13px] !leading-normal !shadow-none"
          placeholder="Escreva sua mensagem…"
        />
        <TicketAttachmentPicker v-model="attachments" />
        <div class="flex justify-end">
          <BaseButton type="submit" size="sm" class="!shadow-none" :loading="submitting">
            <Send class="size-4" />
            Enviar comentário
          </BaseButton>
        </div>
      </form>
    </div>
  </section>
  <section v-if="ticket.attachments?.some((attachment) => attachment.comment_id === null)" class="panel p-5">
    <h2 class="text-sm font-semibold text-gray-900">Anexos do chamado</h2>
    <ul class="mt-3 space-y-2">
      <li
        v-for="attachment in ticket.attachments.filter((item) => item.comment_id === null)"
        :key="attachment.id"
        class="flex items-center justify-between gap-3"
      >
        <span class="truncate text-[13px] text-gray-700">{{ attachment.original_name }}</span>
        <BaseButton variant="ghost" size="sm" class="!shadow-none" @click="download(attachment)">
          <Download class="size-4" />
          Baixar
        </BaseButton>
      </li>
    </ul>
  </section>
</template>
