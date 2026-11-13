<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api, ApiError } from '@/api'
import { useSitio, useUi } from '@/stores'
import { fecha, hoyISO, moneda } from '@/utils'
import type { AvisoPago } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const sitio = useSitio()
const ui = useUi()
const MEDIOS = { transferencia: 'Transferencia', deposito: 'Depósito', efectivo: 'Efectivo', cheque: 'Cheque', otro: 'Otro' } as const
const ESTADOS = { pendiente: ['En revisión', 'bg-amber-100 text-amber-800'], procesado: ['Acreditado', 'bg-emerald-100 text-emerald-800'], rechazado: ['Rechazado', 'bg-red-100 text-red-700'] } as const

const f = reactive({ fecha_pago: hoyISO(), importe: '', medio: 'transferencia', referencia: '', observaciones: '' })
const archivo = ref<File | null>(null)
const inputArchivo = ref<HTMLInputElement | null>(null)
const enviando = ref(false)
const error = ref('')
const avisos = ref<AvisoPago[]>([])
const datosPago = ref('')

async function cargar(): Promise<void> {
  avisos.value = (await api.get<{ items: AvisoPago[] }>('/cuenta/pagos')).items
}
onMounted(async () => {
  cargar().catch(() => undefined)
  api.get<{ datosPago: string }>('/cuenta/resumen').then((r) => { datosPago.value = r.datosPago }).catch(() => undefined)
})

function elegir(e: Event): void {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null
  if (file && file.size > 8 * 1024 * 1024) { ui.avisar('El archivo supera los 8 MB', 'error'); return }
  archivo.value = file
}

async function enviar(): Promise<void> {
  error.value = ''
  enviando.value = true
  const fd = new FormData()
  Object.entries(f).forEach(([k, v]) => fd.append(k, String(v)))
  if (archivo.value) fd.append('comprobante', archivo.value)
  try {
    await api.post('/cuenta/pagos', fd)
    ui.avisar('¡Gracias! Recibimos el aviso de tu pago.', 'ok', 4000)
    Object.assign(f, { fecha_pago: hoyISO(), importe: '', referencia: '', observaciones: '' })
    archivo.value = null
    if (inputArchivo.value) inputArchivo.value.value = ''
    await cargar()
  } catch (e) { error.value = (e as ApiError).message } finally { enviando.value = false }
}
</script>

<template>
  <div v-if="!sitio.sitio?.avisosPagoHabilitados" class="card p-6 text-slate-600">Para informar un pago, comunicate con nosotros.</div>
  <div v-else class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <div class="space-y-6">
      <form class="card grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="enviar">
        <div class="sm:col-span-2">
          <h2 class="text-lg font-bold">Informar un pago</h2>
          <p class="text-sm text-slate-500">Avisanos de tu transferencia o depósito. Lo verificamos y lo imputamos a tu cuenta.</p>
        </div>
        <div><label class="label" for="fp">Fecha del pago</label><input id="fp" v-model="f.fecha_pago" type="date" :max="hoyISO()" class="input" required /></div>
        <div><label class="label" for="imp">Importe</label><input id="imp" v-model="f.importe" type="number" step="0.01" min="0.01" inputmode="decimal" class="input" placeholder="0,00" required /></div>
        <div><label class="label" for="med">Medio</label>
          <select id="med" v-model="f.medio" class="input"><option v-for="(t, k) in MEDIOS" :key="k" :value="k">{{ t }}</option></select>
        </div>
        <div><label class="label" for="ref">N° de operación / referencia</label><input id="ref" v-model="f.referencia" class="input" maxlength="100" /></div>
        <div class="sm:col-span-2"><label class="label" for="obs">Observaciones</label><textarea id="obs" v-model="f.observaciones" rows="2" class="input" maxlength="500" placeholder="Ej: corresponde a la factura 0005-00001234" /></div>
        <div class="sm:col-span-2">
          <label class="label">Comprobante (foto o PDF, opcional)</label>
          <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-slate-200 p-4 text-sm text-slate-500 hover:border-brand-300">
            <AppIcon name="upload" class="text-brand-600" />
            <span class="min-w-0 truncate">{{ archivo ? archivo.name : 'Tocá para elegir el archivo o sacar una foto' }}</span>
            <input ref="inputArchivo" type="file" accept="image/*,application/pdf" class="hidden" @change="elegir" />
          </label>
        </div>
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700 sm:col-span-2">{{ error }}</p>
        <button type="submit" class="btn-primary py-3 sm:col-span-2" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Informar pago' }}</button>
      </form>

      <div v-if="avisos.length" class="card">
        <p class="border-b border-slate-100 p-4 font-bold">Pagos informados</p>
        <ul class="divide-y divide-slate-100">
          <li v-for="a in avisos" :key="a.id" class="flex items-center justify-between gap-3 p-4">
            <div><p class="font-semibold">{{ moneda(a.importe) }} <span class="font-normal text-slate-500">· {{ MEDIOS[a.medio as keyof typeof MEDIOS] ?? a.medio }}</span></p>
              <p class="text-xs text-slate-500">Pagado el {{ fecha(a.fecha_pago) }}{{ a.referencia ? ' · ' + a.referencia : '' }}</p></div>
            <span class="badge" :class="ESTADOS[a.estado][1]">{{ ESTADOS[a.estado][0] }}</span>
          </li>
        </ul>
      </div>
    </div>

    <aside v-if="datosPago" class="card h-fit p-5">
      <p class="mb-2 flex items-center gap-2 font-bold"><AppIcon name="wallet" :size="18" class="text-brand-600" />Datos para transferir</p>
      <p class="whitespace-pre-line font-mono text-sm text-slate-700">{{ datosPago }}</p>
    </aside>
  </div>
</template>
