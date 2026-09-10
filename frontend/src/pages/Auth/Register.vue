<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import BaseButton from '@/components/BaseButton.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const submitting = ref(false)

async function onSubmit() {
  error.value = ''
  submitting.value = true

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    router.push({ name: 'customer.home' })
  } catch (e) {
    const errors = e?.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Falha no cadastro.'
  } finally {
    submitting.value = false
  }
}
</script>
<template>
  <AuthCard title="Criar conta" subtitle="Cadastro de acesso">
    <form class="space-y-4" @submit.prevent="onSubmit">
      <div>
        <label class="field-label" for="name">Nome</label>
        <input id="name" v-model="name" type="text" required class="field" />
      </div>
      <div>
        <label class="field-label" for="email">E-mail</label>
        <input id="email" v-model="email" type="email" required class="field" />
      </div>
      <div>
        <label class="field-label" for="password">Senha</label>
        <input id="password" v-model="password" type="password" required class="field" />
      </div>
      <div>
        <label class="field-label" for="password_confirmation">Confirmar senha</label>
        <input
          id="password_confirmation"
          v-model="passwordConfirmation"
          type="password"
          required
          class="field"
        />
      </div>
      <p
        v-if="error"
        class="rounded-md bg-red-100 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200"
      >
        {{ error }}
      </p>
      <BaseButton type="submit" :disabled="submitting" block>Cadastrar</BaseButton>
    </form>
    <template #footer>
      Já tem uma conta?
      <router-link class="font-medium text-theme-light-700 hover:underline" :to="{ name: 'login' }">
        Entrar
      </router-link>
    </template>
  </AuthCard>
</template>