<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api, ApiError } from '@/api'
import { useSitio } from '@/stores'
import { fecha, moneda } from '@/utils'
import type { Comprobante } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const sitio = useSitio()
const soloPendientes = ref(true)
const items = ref<Comprobante[] | null>(null)
const error = ref('')
const total = computed(() => (items.value ?? []).reduce((s, c) => s + c.saldo, 0))

async function cargar(): Promise<void> {
  items.value = null
  error.value = ''
  try { items.value = (await api.get<{ items: Comprobante[] }>('/cuenta/comprobantes', { pendientes: soloPendientes.value ? 1 : '' })).items }
  catch (e) { error.value = (e as ApiError).message }
}
watch(soloPendientes, cargar, { immediate: true })
const ver = (id: number) => window.open(`/api/cuenta/comprobante?id=${id}`, '_blank')
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 text-sm font-medium">
        <button type="button" class="rounded-lg px-4 py-2" :class="soloPendientes ? 'bg-brand-600 text-white' : 'text-slate-600'" @click="soloPendientes = true">Pendientes de pago</button>
        <button type="button" class="rounded-lg px-4 py-2" :class="!soloPendientes ? 'bg-brand-600 text-white' : 'text-slate-600'" @click="soloPendientes = false">Último año</button>
      </div>
      <p v-if="soloPendientes && items?.length" class="text-sm text-slate-600">Total pendiente: <b class="text-lg text-slate-900">{{ moneda(total) }}</b></p>
    </div>

    <p v-if="error" class="card p-6 text-red-600">{{ error }}</p>
    <div v-else-if="!items" class="skeleton h-48" />
    <div v-else-if="!items.length" class="card p-10 text-center text-slate-500">
      <AppIcon name="check" :size="40" class="mx-auto text-emerald-500" />
      <p class="mt-2 font-semibold">{{ soloPendientes ? 'No tenés facturas pendientes' : 'No hay comprobantes en el último año' }}</p>
    </div>
    <ul v-else class="card divide-y divide-slate-100">
      <li v-for="c in items" :key="c.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
        <div class="min-w-0">
          <p class="font-semibold">{{ c.tipo }} <span class="font-normal text-slate-500">{{ c.numero }}</span></p>
          <p class="text-xs text-slate-500">
            Emitida {{ fecha(c.fecha) }}
            <template v-if="c.vencimiento && c.saldo > 0"> · <span :class="c.vencida ? 'font-semibold text-red-600' : ''">vence {{ fecha(c.vencimiento) }}{{ c.vencida ? ' (vencida)' : '' }}</span></template>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-right">
            <p class="font-bold">{{ moneda(c.saldo > 0 ? c.saldo : c.total) }}</p>
            <p v-if="c.saldo > 0 && c.saldo < c.total - 0.01" class="text-xs text-slate-400">de {{ moneda(c.total) }}</p>
            <p v-else-if="!soloPendientes" class="text-xs" :class="c.saldo > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ c.saldo > 0 ? 'Pendiente' : 'Pagada' }}</p>
          </div>
          <button type="button" class="btn-secondary px-3" title="Ver / imprimir / guardar PDF" @click="ver(c.id)"><AppIcon name="eye" :size="18" /><span class="hidden sm:inline">Ver</span></button>
        </div>
      </li>
    </ul>
    <p v-if="soloPendientes && items?.length && sitio.sitio?.avisosPagoHabilitados" class="text-center text-sm text-slate-600">
      ¿Ya pagaste? <RouterLink :to="{ name: 'pagos' }" class="font-semibold text-brand-600 hover:underline">Informá tu pago</RouterLink>
    </p>
  </div>
</template>
