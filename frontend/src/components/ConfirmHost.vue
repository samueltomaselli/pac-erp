<script setup>
import BaseButton from '@/components/BaseButton.vue'
import BaseModal from '@/components/BaseModal.vue'
import { confirmState, settleConfirm } from '@/composables/useConfirm'
</script>
<template>
  <BaseModal
    :open="confirmState.open"
    :title="confirmState.title"
    size="sm"
    @close="settleConfirm(false)"
  >
    <p v-if="confirmState.message" class="text-sm leading-relaxed text-gray-600">
      {{ confirmState.message }}
    </p>
    <template #footer>
      <BaseButton variant="secondary" @click="settleConfirm(false)">
        {{ confirmState.cancelLabel }}
      </BaseButton>
      <BaseButton
        :variant="confirmState.tone === 'danger' ? 'danger' : 'primary'"
        data-autofocus
        @click="settleConfirm(true)"
      >
        {{ confirmState.confirmLabel }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
