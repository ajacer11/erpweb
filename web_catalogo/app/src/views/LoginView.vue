<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api'
import { useCarrito, useSitio } from '@/stores'
import AppIcon from '@/components/AppIcon.vue'

const sitio = useSitio()
const carrito = useCarrito()
const route = useRoute()
const router = useRouter()

const usuario = ref('')
const clave = ref('')
const ver = ref(false)
const enviando = ref(false)
const error = ref('')

async function ingresar(): Promise<void> {
  error.value = ''
  enviando.value = true
  try {
    await sitio.login(usuario.value.trim(), clave.value)
    carrito.actualizarPrecios().catch(() => undefined) // ahora con la lista del cliente
    const volver = String(route.query.volver ?? '')
    router.replace(volver.startsWith('/') && !volver.startsWith('//') ? volver : { name: 'cuenta' })
  } catch (e) {
    error.value = (e as ApiError).message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="container-page flex min-h-[70vh] items-center justify-center py-10">
    <div class="w-full max-w-md">
      <div class="card p-6 sm:p-8">
        <div class="mb-6 text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><AppIcon name="user" :size="28" /></div>
          <h1 class="text-2xl font-extrabold">Área de clientes</h1>
          <p class="mt-1 text-sm text-slate-500">Ingresá para ver tus precios, hacer pedidos y consultar tu cuenta.</p>
        </div>
        <form class="space-y-4" @submit.prevent="ingresar">
          <div>
            <label class="label" for="usr">Usuario o mail</label>
            <input id="usr" v-model="usuario" class="input" autocomplete="username" autocapitalize="none" required autofocus />
          </div>
          <div>
            <label class="label" for="pwd">Contraseña</label>
            <div class="relative">
              <input id="pwd" v-model="clave" :type="ver ? 'text' : 'password'" class="input pr-11" autocomplete="current-password" required />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-slate-400 hover:text-slate-600" :aria-label="ver ? 'Ocultar' : 'Mostrar'" @click="ver = !ver"><AppIcon name="eye" :size="18" /></button>
            </div>
          </div>
          <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
          <button type="submit" class="btn-primary w-full py-3 text-base" :disabled="enviando">{{ enviando ? 'Ingresando...' : 'Ingresar' }}</button>
        </form>
        <RouterLink :to="{ name: 'recuperar' }" class="mt-4 block text-center text-sm font-semibold text-brand-600 hover:underline">Olvidé mi contraseña</RouterLink>
      </div>
      <div v-if="sitio.sitio?.registroHabilitado" class="mt-4 text-center text-sm text-slate-600">
        ¿Todavía no sos cliente? <RouterLink :to="{ name: 'registro' }" class="font-semibold text-brand-600 hover:underline">Pedí tu acceso</RouterLink>
      </div>
    </div>
  </div>
</template>
