<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api'
import { useCarrito, useSitio } from '@/stores'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const sitio = useSitio()
const carrito = useCarrito()

const token = String(route.query.token ?? '')
const info = ref<{ razon: string; usuario: string; tipo: string } | null>(null)
const cargando = ref(true)
const invalido = ref('')
const clave = ref('')
const clave2 = ref('')
const enviando = ref(false)
const error = ref('')

onMounted(async () => {
  try { info.value = await api.get('/auth/token', { token }) }
  catch (e) { invalido.value = (e as ApiError).message }
  finally { cargando.value = false }
})

async function guardar(): Promise<void> {
  error.value = ''
  if (clave.value.length < 8) { error.value = 'La contraseña tiene que tener al menos 8 caracteres.'; return }
  if (clave.value !== clave2.value) { error.value = 'Las contraseñas no coinciden.'; return }
  enviando.value = true
  try {
    await sitio.activar(token, clave.value)
    carrito.actualizarPrecios().catch(() => undefined)
    router.replace({ name: 'cuenta' })
  } catch (e) { error.value = (e as ApiError).message } finally { enviando.value = false }
}
</script>

<template>
  <div class="container-page flex min-h-[70vh] items-center justify-center py-10">
    <div class="card w-full max-w-md p-6 sm:p-8">
      <p v-if="cargando" class="py-6 text-center text-slate-500">Verificando el link...</p>
      <div v-else-if="invalido" class="text-center">
        <AppIcon name="alert" :size="40" class="mx-auto text-amber-500" />
        <p class="mt-3 font-semibold">{{ invalido }}</p>
        <RouterLink :to="{ name: 'recuperar' }" class="btn-primary mt-4">Pedir un link nuevo</RouterLink>
      </div>
      <template v-else-if="info">
        <div class="mb-6 text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><AppIcon name="lock" :size="28" /></div>
          <h1 class="text-2xl font-extrabold">{{ info.tipo === 'invitacion' ? '¡Bienvenido!' : 'Nueva contraseña' }}</h1>
          <p class="mt-1 text-sm text-slate-500"><b>{{ info.razon }}</b> · usuario <b>{{ info.usuario }}</b></p>
          <p v-if="info.tipo === 'invitacion'" class="mt-2 text-sm text-slate-500">Elegí tu contraseña para entrar al área de clientes.</p>
        </div>
        <form class="space-y-4" @submit.prevent="guardar">
          <div><label class="label" for="c1">Contraseña (mínimo 8 caracteres)</label><input id="c1" v-model="clave" type="password" class="input" autocomplete="new-password" required /></div>
          <div><label class="label" for="c2">Repetila</label><input id="c2" v-model="clave2" type="password" class="input" autocomplete="new-password" required /></div>
          <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
          <button type="submit" class="btn-primary w-full py-3" :disabled="enviando">{{ enviando ? 'Guardando...' : 'Guardar e ingresar' }}</button>
        </form>
      </template>
    </div>
  </div>
</template>
