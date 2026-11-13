<script setup lang="ts">
import { ref } from 'vue'
import { api, ApiError } from '@/api'
import AppIcon from '@/components/AppIcon.vue'

const dato = ref('')
const enviando = ref(false)
const mensaje = ref('')
const error = ref('')

async function enviar(): Promise<void> {
  error.value = ''
  enviando.value = true
  try {
    const r = await api.post<{ mensaje: string }>('/auth/recuperar', { usuario: dato.value.trim() })
    mensaje.value = r.mensaje
  } catch (e) { error.value = (e as ApiError).message } finally { enviando.value = false }
}
</script>

<template>
  <div class="container-page flex min-h-[70vh] items-center justify-center py-10">
    <div class="card w-full max-w-md p-6 sm:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><AppIcon name="lock" :size="28" /></div>
        <h1 class="text-2xl font-extrabold">Recuperar contraseña</h1>
        <p class="mt-1 text-sm text-slate-500">Te mandamos un mail con un link para elegir una nueva.</p>
      </div>
      <div v-if="mensaje" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ mensaje }}</div>
      <form v-else class="space-y-4" @submit.prevent="enviar">
        <div>
          <label class="label" for="dato">Usuario o mail</label>
          <input id="dato" v-model="dato" class="input" autocapitalize="none" required autofocus />
        </div>
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <button type="submit" class="btn-primary w-full py-3" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Enviarme el link' }}</button>
      </form>
      <RouterLink :to="{ name: 'login' }" class="mt-4 block text-center text-sm font-semibold text-brand-600 hover:underline">Volver a ingresar</RouterLink>
    </div>
  </div>
</template>
