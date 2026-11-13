<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api'
import { useCarrito, useSitio, useUi } from '@/stores'
import { ESTADOS_PEDIDO, fecha, moneda } from '@/utils'
import type { PedidoDetalle, Producto } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const sitio = useSitio()
const carrito = useCarrito()
const ui = useUi()
const p = ref<PedidoDetalle | null>(null)
const error = ref('')
const repitiendo = ref(false)

onMounted(async () => {
  try { p.value = await api.get<PedidoDetalle>('/cuenta/pedido', { numero: route.params.numero }) } catch (e) { error.value = (e as ApiError).message }
})

/** Carga al pedido actual los mismos productos y cantidades (con los precios de hoy). */
async function repetir(): Promise<void> {
  if (!p.value) return
  repitiendo.value = true
  try {
    const r = await api.post<{ items: Producto[] }>('/carrito/precios', { codigos: p.value.lineas.map((l) => l.codigo) })
    const disponibles = new Map(r.items.map((x) => [x.codigo, x]))
    let agregados = 0
    for (const l of p.value.lineas) {
      const prod = disponibles.get(l.codigo)
      if (prod && prod.precio !== null) { carrito.agregar(prod, l.cantidad); agregados++ }
    }
    const faltan = p.value.lineas.length - agregados
    ui.avisar(faltan ? `Agregamos ${agregados} productos. ${faltan} ya no están disponibles.` : 'Agregamos todos los productos a tu pedido', faltan ? 'info' : 'ok', 4000)
    router.push({ name: 'pedido' })
  } catch (e) { ui.avisar((e as ApiError).message, 'error') } finally { repitiendo.value = false }
}
</script>

<template>
  <div>
    <RouterLink :to="{ name: 'pedidos' }" class="btn-ghost -ml-3 mb-3"><AppIcon name="arrow-left" :size="18" />Mis pedidos</RouterLink>
    <p v-if="error" class="card p-6 text-red-600">{{ error }}</p>
    <div v-else-if="!p" class="skeleton h-64" />
    <div v-else class="card">
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-5">
        <div>
          <h2 class="text-xl font-extrabold">Pedido #{{ p.numero }}</h2>
          <p class="text-sm text-slate-500">{{ fecha(p.fecha, true) }}</p>
          <p v-if="p.observaciones" class="mt-1 text-sm text-slate-600">Obs.: {{ p.observaciones }}</p>
        </div>
        <span class="badge text-xs" :class="ESTADOS_PEDIDO[p.estado].clase">{{ ESTADOS_PEDIDO[p.estado].texto }}</span>
      </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="(l, i) in p.lineas" :key="i" class="flex items-center justify-between gap-3 px-5 py-3">
          <div class="min-w-0">
            <p class="font-medium">{{ l.descripcion }}</p>
            <p class="text-xs text-slate-400">Cód. {{ l.codigo }} · {{ l.cantidad }} × {{ moneda(l.precio) }}</p>
          </div>
          <span class="shrink-0 font-semibold">{{ moneda(l.subtotal) }}</span>
        </li>
      </ul>
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 p-5">
        <p>Total <b class="text-xl">{{ moneda(p.total) }}</b></p>
        <button v-if="sitio.puedePedir" type="button" class="btn-primary" :disabled="repitiendo" @click="repetir"><AppIcon name="repeat" :size="18" />Repetir este pedido</button>
      </div>
    </div>
  </div>
</template>
