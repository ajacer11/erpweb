<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api'
import { ESTADOS_PEDIDO, fecha, moneda } from '@/utils'
import type { PedidoResumen } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const items = ref<PedidoResumen[] | null>(null)
const error = ref('')
onMounted(async () => {
  try { items.value = (await api.get<{ items: PedidoResumen[] }>('/cuenta/pedidos')).items } catch (e) { error.value = (e as ApiError).message }
})
</script>

<template>
  <p v-if="error" class="card p-6 text-red-600">{{ error }}</p>
  <div v-else-if="!items" class="skeleton h-48" />
  <div v-else-if="!items.length" class="card p-10 text-center text-slate-500">
    <AppIcon name="box" :size="40" class="mx-auto text-slate-300" />
    <p class="mt-2 font-semibold">Todavía no hiciste pedidos</p>
    <RouterLink :to="{ name: 'catalogo' }" class="btn-primary mt-4">Hacer mi primer pedido</RouterLink>
  </div>
  <ul v-else class="card divide-y divide-slate-100">
    <li v-for="p in items" :key="p.numero">
      <RouterLink :to="{ name: 'pedido-detalle', params: { numero: p.numero } }" class="flex items-center justify-between gap-3 p-4 hover:bg-slate-50">
        <div class="min-w-0">
          <p class="font-semibold">Pedido #{{ p.numero }} <span v-if="p.web" class="ml-1 text-xs font-medium text-slate-400">web</span></p>
          <p class="text-xs text-slate-500">{{ fecha(p.fecha) }} · {{ p.items }} {{ p.items === 1 ? 'producto' : 'productos' }}</p>
          <p v-if="p.observaciones" class="truncate text-xs text-slate-400">{{ p.observaciones }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
          <span class="badge" :class="ESTADOS_PEDIDO[p.estado].clase">{{ ESTADOS_PEDIDO[p.estado].texto }}</span>
          <span class="hidden font-bold sm:inline">{{ moneda(p.total) }}</span>
          <AppIcon name="chevron-right" :size="16" class="text-slate-300" />
        </div>
      </RouterLink>
    </li>
  </ul>
</template>
