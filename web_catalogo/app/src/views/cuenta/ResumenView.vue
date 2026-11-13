<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api'
import { useSitio } from '@/stores'
import { ESTADOS_PEDIDO, fecha, linkWhatsapp, moneda, whatsappArgentina } from '@/utils'
import type { ResumenCuenta } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import IconWhatsapp from '@/components/IconWhatsapp.vue'

const sitio = useSitio()
const r = ref<ResumenCuenta | null>(null)
const error = ref('')

onMounted(async () => {
  try { r.value = await api.get<ResumenCuenta>('/cuenta/resumen') } catch (e) { error.value = (e as ApiError).message }
})
</script>

<template>
  <p v-if="error" class="card p-6 text-red-600">{{ error }}</p>
  <div v-else-if="!r" class="grid gap-4 sm:grid-cols-3"><div v-for="i in 3" :key="i" class="skeleton h-28" /></div>
  <div v-else class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
      <RouterLink :to="{ name: 'movimientos' }" class="card p-5 transition hover:shadow-card-hover">
        <p class="text-sm text-slate-500">Saldo de tu cuenta</p>
        <p class="mt-1 text-2xl font-extrabold" :class="r.saldo > 0.009 ? 'text-red-600' : 'text-emerald-600'">{{ moneda(Math.abs(r.saldo)) }}</p>
        <p class="text-xs text-slate-500">{{ r.saldo > 0.009 ? 'a pagar' : r.saldo < -0.009 ? 'a tu favor' : 'estás al día' }}</p>
      </RouterLink>
      <RouterLink :to="{ name: 'comprobantes' }" class="card p-5 transition hover:shadow-card-hover">
        <p class="text-sm text-slate-500">Facturas pendientes</p>
        <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ r.pendientes.cantidad }}</p>
        <p class="text-xs text-slate-500">por {{ moneda(r.pendientes.total) }}</p>
      </RouterLink>
      <div class="card p-5" :class="r.pendientes.vencido > 0 ? 'border-red-200 bg-red-50' : ''">
        <p class="text-sm text-slate-500">Vencido</p>
        <p class="mt-1 text-2xl font-extrabold" :class="r.pendientes.vencido > 0 ? 'text-red-600' : 'text-slate-900'">{{ moneda(r.pendientes.vencido) }}</p>
        <RouterLink v-if="sitio.sitio?.avisosPagoHabilitados && r.saldo > 0" :to="{ name: 'pagos' }" class="text-xs font-semibold text-brand-600 hover:underline">Informar un pago</RouterLink>
      </div>
    </div>

    <div v-if="r.datosPago" class="card p-5">
      <p class="mb-2 flex items-center gap-2 font-bold"><AppIcon name="wallet" :size="18" class="text-brand-600" />Datos para transferir</p>
      <p class="whitespace-pre-line font-mono text-sm text-slate-700">{{ r.datosPago }}</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between border-b border-slate-100 p-4">
        <p class="font-bold">Últimos pedidos</p>
        <RouterLink :to="{ name: 'pedidos' }" class="text-sm font-semibold text-brand-600 hover:underline">Ver todos</RouterLink>
      </div>
      <ul v-if="r.ultimosPedidos.length" class="divide-y divide-slate-100">
        <li v-for="p in r.ultimosPedidos" :key="p.numero">
          <RouterLink :to="{ name: 'pedido-detalle', params: { numero: p.numero } }" class="flex items-center justify-between gap-3 p-4 hover:bg-slate-50">
            <div><p class="font-semibold">Pedido #{{ p.numero }}</p><p class="text-xs text-slate-500">{{ fecha(p.fecha) }}</p></div>
            <div class="flex items-center gap-3">
              <span class="badge" :class="ESTADOS_PEDIDO[p.estado].clase">{{ ESTADOS_PEDIDO[p.estado].texto }}</span>
              <span class="font-bold">{{ moneda(p.total) }}</span>
              <AppIcon name="chevron-right" :size="16" class="text-slate-300" />
            </div>
          </RouterLink>
        </li>
      </ul>
      <div v-else class="p-6 text-center text-sm text-slate-500">Todavía no hiciste pedidos. <RouterLink :to="{ name: 'catalogo' }" class="font-semibold text-brand-600">Ver el catálogo</RouterLink></div>
    </div>

    <div v-if="r.datos.vendedor" class="card flex flex-wrap items-center justify-between gap-3 p-5">
      <div><p class="text-sm text-slate-500">Tu vendedor</p><p class="font-bold">{{ r.datos.vendedor }}</p></div>
      <a v-if="r.datos.vendedor_celular" :href="linkWhatsapp(whatsappArgentina(String(r.datos.vendedor_celular)))" target="_blank" rel="noopener" class="btn-whatsapp"><IconWhatsapp />Escribirle</a>
    </div>
  </div>
</template>
