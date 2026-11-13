<script setup lang="ts">
import { reactive, ref } from 'vue'
import { api, ApiError } from '@/api'
import { useSitio } from '@/stores'
import AppIcon from '@/components/AppIcon.vue'

const sitio = useSitio()
const f = reactive({ razon: '', cuit: '', contacto: '', email: '', telefono: '', localidad: '', mensaje: '', web: '' })
const enviando = ref(false)
const listo = ref(false)
const error = ref('')

async function enviar(): Promise<void> {
  error.value = ''
  enviando.value = true
  try { await api.post('/auth/registro', f); listo.value = true; window.scrollTo({ top: 0 }) }
  catch (e) { error.value = (e as ApiError).message }
  finally { enviando.value = false }
}
</script>

<template>
  <div class="container-page max-w-2xl py-8">
    <div v-if="!sitio.sitio?.registroHabilitado" class="card p-8 text-center text-slate-600">Para ser cliente, comunicate con nosotros.</div>
    <div v-else-if="listo" class="card animate-fade-up p-8 text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><AppIcon name="check" :size="32" :stroke-width="3" /></div>
      <h1 class="mt-4 text-2xl font-extrabold">¡Recibimos tu solicitud!</h1>
      <p class="mt-2 text-slate-600">La vamos a revisar y te contactamos. Cuando tu acceso esté listo te llega un mail para crear tu contraseña.</p>
      <RouterLink :to="{ name: 'catalogo' }" class="btn-primary mt-6">Ver el catálogo</RouterLink>
    </div>
    <template v-else>
      <h1 class="text-2xl font-extrabold sm:text-3xl">Quiero ser cliente</h1>
      <p class="mt-1 text-slate-500">Completá tus datos y te habilitamos el acceso para ver precios y hacer pedidos online.</p>
      <form class="card mt-6 grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="enviar">
        <div class="sm:col-span-2"><label class="label" for="r">Nombre o razón social *</label><input id="r" v-model="f.razon" class="input" required maxlength="150" /></div>
        <div><label class="label" for="cuit">CUIT / DNI</label><input id="cuit" v-model="f.cuit" class="input" inputmode="numeric" maxlength="20" /></div>
        <div><label class="label" for="ct">Persona de contacto</label><input id="ct" v-model="f.contacto" class="input" maxlength="100" /></div>
        <div><label class="label" for="em">Mail *</label><input id="em" v-model="f.email" type="email" class="input" required autocapitalize="none" /></div>
        <div><label class="label" for="tel">Teléfono / WhatsApp</label><input id="tel" v-model="f.telefono" type="tel" class="input" maxlength="50" /></div>
        <div class="sm:col-span-2"><label class="label" for="loc">Localidad</label><input id="loc" v-model="f.localidad" class="input" maxlength="100" /></div>
        <div class="sm:col-span-2"><label class="label" for="msg">Contanos sobre tu negocio</label><textarea id="msg" v-model="f.mensaje" rows="4" class="input" maxlength="1000" /></div>
        <input v-model="f.web" type="text" name="web" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700 sm:col-span-2">{{ error }}</p>
        <button type="submit" class="btn-primary py-3 sm:col-span-2" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Enviar solicitud' }}</button>
      </form>
      <p class="mt-4 text-center text-sm text-slate-600">¿Ya tenés acceso? <RouterLink :to="{ name: 'login' }" class="font-semibold text-brand-600 hover:underline">Ingresá</RouterLink></p>
    </template>
  </div>
</template>
