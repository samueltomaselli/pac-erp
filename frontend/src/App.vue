<script setup>
import ConfirmHost from '@/components/ConfirmHost.vue'
import ToastHost from '@/components/ToastHost.vue'
</script>

<template>
  <router-view />
  <ConfirmHost />
  <ToastHost />
</template>
