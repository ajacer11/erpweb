<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api'
import { fecha, hoyISO, moneda } from '@/utils'
import type { Movimiento } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

interface Respuesta { desde: string; hasta: string; saldoAnterior: number; movimientos: Movimiento[]; saldoActual: number }
const r = ref<Respuesta | null>(null)
const cargando = ref(false)
const error = ref('')
const desde = ref('')
const hasta = ref(hoyISO())
const periodos = [{ meses: 3, texto: '3 meses' }, { meses: 6, texto: '6 meses' }, { meses: 12, texto: '1 año' }]

function haceMeses(n: number): string {
  const d = new Date(); d.setMonth(d.getMonth() - n)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}
async function cargar(): Promise<void> {
  cargando.value = true
  error.value = ''
  try {
    r.value = await api.get<Respuesta>('/cuenta/movimientos', { desde: desde.value, hasta: hasta.value })
    desde.value = r.value.desde
  } catch (e) { error.value = (e as ApiError).message } finally { cargando.value = false }
}
function periodo(meses: number): void { desde.value = haceMeses(meses); hasta.value = hoyISO(); cargar() }
const verComprobante = (id: number) => window.open(`/api/cuenta/comprobante?id=${id}`, '_blank')
onMounted(cargar)
</script>

<template>
  <div class="space-y-4">
    <div class="card flex flex-wrap items-end gap-3 p-4">
      <div class="flex gap-2">
        <button v-for="p in periodos" :key="p.meses" type="button" class="chip" @click="periodo(p.meses)">{{ p.texto }}</button>
      </div>
      <div class="flex flex-1 flex-wrap items-end gap-2">
        <div><label class="label text-xs" for="d">Desde</label><input id="d" v-model="desde" type="date" class="input py-2" /></div>
        <div><label class="label text-xs" for="h">Hasta</label><input id="h" v-model="hasta" type="date" class="input py-2" /></div>
        <button type="button" class="btn-primary" @click="cargar">Ver</button>
      </div>
    </div>

    <p v-if="error" class="card p-6 text-red-600">{{ error }}</p>
    <div v-else-if="r" class="card overflow-hidden" :class="{ 'opacity-60': cargando }">
      <div class="flex items-center justify-between bg-slate-50 px-4 py-3 text-sm">
        <span class="text-slate-500">Saldo al {{ fecha(r.desde) }}</span><b>{{ moneda(r.saldoAnterior) }}</b>
      </div>
      <!-- Celular: tarjetas -->
      <ul class="divide-y divide-slate-100 md:hidden">
        <li v-for="(m, i) in r.movimientos" :key="i" class="flex items-center justify-between gap-3 p-4">
          <div class="min-w-0">
            <p class="font-semibold">{{ m.concepto }}</p>
            <p class="text-xs text-slate-500">{{ fecha(m.fecha) }} · {{ m.numero }}</p>
            <button v-if="m.comprobanteId" type="button" class="mt-1 text-xs font-semibold text-brand-600" @click="verComprobante(m.comprobanteId)">Ver comprobante</button>
          </div>
          <div class="text-right">
            <p class="font-bold" :class="m.debe > 0 ? 'text-slate-900' : 'text-emerald-600'">{{ m.debe > 0 ? moneda(m.debe) : '- ' + moneda(m.haber) }}</p>
            <p class="text-xs text-slate-400">Saldo {{ moneda(m.saldo) }}</p>
          </div>
        </li>
      </ul>
      <!-- Escritorio: tabla -->
      <table class="hidden w-full text-sm md:table">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase text-slate-500">
          <th class="px-4 py-2">Fecha</th><th class="px-4 py-2">Comprobante</th><th class="px-4 py-2 text-right">Debe</th><th class="px-4 py-2 text-right">Haber</th><th class="px-4 py-2 text-right">Saldo</th><th class="w-10"></th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="(m, i) in r.movimientos" :key="i" class="hover:bg-slate-50">
            <td class="px-4 py-2.5">{{ fecha(m.fecha) }}</td>
            <td class="px-4 py-2.5">{{ m.concepto }} <span class="text-slate-400">{{ m.numero }}</span></td>
            <td class="px-4 py-2.5 text-right">{{ m.debe ? moneda(m.debe) : '' }}</td>
            <td class="px-4 py-2.5 text-right text-emerald-600">{{ m.haber ? moneda(m.haber) : '' }}</td>
            <td class="px-4 py-2.5 text-right font-semibold">{{ moneda(m.saldo) }}</td>
            <td class="pr-3"><button v-if="m.comprobanteId" type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-brand-50 hover:text-brand-600" title="Ver comprobante" @click="verComprobante(m.comprobanteId)"><AppIcon name="eye" :size="16" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!r.movimientos.length" class="p-6 text-center text-sm text-slate-500">No hay movimientos en este período.</p>
      <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-3">
        <span class="font-semibold">Saldo actual</span>
        <span class="text-lg font-extrabold" :class="r.saldoActual > 0.009 ? 'text-red-600' : 'text-emerald-600'">{{ moneda(r.saldoActual) }}</span>
      </div>
    </div>
    <div v-else class="skeleton h-64" />
  </div>
</template>
