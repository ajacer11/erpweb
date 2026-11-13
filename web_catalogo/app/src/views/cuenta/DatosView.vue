<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api'
import { useSitio, useUi } from '@/stores'
import { linkWhatsapp } from '@/utils'
import type { ResumenCuenta } from '@/types'
import IconWhatsapp from '@/components/IconWhatsapp.vue'

const sitio = useSitio()
const ui = useUi()
const datos = ref<ResumenCuenta['datos'] | null>(null)
const actual = ref('')
const nueva = ref('')
const nueva2 = ref('')
const error = ref('')
const guardando = ref(false)

onMounted(async () => {
  try { datos.value = (await api.get<ResumenCuenta>('/cuenta/resumen')).datos } catch (e) { error.value = (e as ApiError).message }
})

async function cambiarClave(): Promise<void> {
  error.value = ''
  if (nueva.value.length < 8) { error.value = 'La contraseña nueva tiene que tener al menos 8 caracteres.'; return }
  if (nueva.value !== nueva2.value) { error.value = 'Las contraseñas nuevas no coinciden.'; return }
  guardando.value = true
  try {
    await api.post('/auth/clave', { actual: actual.value, nueva: nueva.value })
    actual.value = nueva.value = nueva2.value = ''
    ui.avisar('Contraseña actualizada')
  } catch (e) { error.value = (e as ApiError).message } finally { guardando.value = false }
}

const filas: [string, string][] = [
  ['Razón social', 'razon'], ['CUIT / DNI', 'numdoc'], ['Condición fiscal', 'condicionfiscal'], ['Domicilio', 'domicilio'],
  ['Localidad', 'localidad'], ['Provincia', 'provincia'], ['Teléfono', 'telefono'], ['Celular', 'celular'], ['Mail', 'mail'],
]
</script>

<template>
  <div class="grid gap-6 xl:grid-cols-2">
    <div class="card p-5">
      <h2 class="mb-3 text-lg font-bold">Mis datos</h2>
      <dl v-if="datos" class="divide-y divide-slate-100 text-sm">
        <div v-for="[t, k] in filas" v-show="datos[k]" :key="k" class="grid grid-cols-[130px_1fr] gap-2 py-2"><dt class="text-slate-500">{{ t }}</dt><dd class="font-medium break-words">{{ datos[k] }}</dd></div>
      </dl>
      <div v-else class="skeleton h-40" />
      <p class="mt-4 text-sm text-slate-500">¿Algún dato no es correcto? Avisanos y lo corregimos.</p>
      <a v-if="sitio.whatsapp" :href="linkWhatsapp(sitio.whatsapp, `Hola! Soy ${sitio.cliente?.razon}, quiero actualizar mis datos.`)" target="_blank" rel="noopener" class="btn-whatsapp mt-2"><IconWhatsapp />Pedir cambio de datos</a>
    </div>

    <form class="card space-y-4 p-5" @submit.prevent="cambiarClave">
      <h2 class="text-lg font-bold">Cambiar contraseña</h2>
      <p v-if="datos" class="text-sm text-slate-500">Usuario: <b>{{ datos.usuario }}</b><template v-if="datos.email_acceso"> · Mail: <b>{{ datos.email_acceso }}</b></template></p>
      <div><label class="label" for="a">Contraseña actual</label><input id="a" v-model="actual" type="password" class="input" autocomplete="current-password" required /></div>
      <div><label class="label" for="n1">Contraseña nueva</label><input id="n1" v-model="nueva" type="password" class="input" autocomplete="new-password" required /></div>
      <div><label class="label" for="n2">Repetir contraseña nueva</label><input id="n2" v-model="nueva2" type="password" class="input" autocomplete="new-password" required /></div>
      <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <button type="submit" class="btn-primary w-full" :disabled="guardando">{{ guardando ? 'Guardando...' : 'Cambiar contraseña' }}</button>
    </form>
  </div>
</template>
