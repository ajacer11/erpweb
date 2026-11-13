<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCarrito, useSitio, useUi } from '@/stores'
import { moneda } from '@/utils'
import AppIcon from './AppIcon.vue'
import QtyStepper from './QtyStepper.vue'

const carrito = useCarrito()
const sitio = useSitio()
const ui = useUi()
const route = useRoute()

watch(() => route.fullPath, () => { ui.carritoAbierto = false })
watch(() => ui.carritoAbierto, (abierto) => { if (abierto) carrito.actualizarPrecios().catch(() => undefined) })
</script>

<template>
  <Transition name="fade">
    <div v-if="ui.carritoAbierto" class="fixed inset-0 z-50 bg-slate-900/50" @click="ui.carritoAbierto = false" />
  </Transition>
  <Transition name="slide">
    <aside v-if="ui.carritoAbierto" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl" aria-label="Mi pedido">
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <h2 class="text-lg font-bold">Mi pedido <span class="text-sm font-medium text-slate-400">({{ carrito.cantidadItems }})</span></h2>
        <button type="button" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Cerrar" @click="ui.carritoAbierto = false"><AppIcon name="close" /></button>
      </div>

      <div v-if="!carrito.items.length" class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center text-slate-500">
        <AppIcon name="cart" :size="48" :stroke-width="1.5" class="text-slate-300" />
        <p>Todavía no agregaste productos.</p>
        <RouterLink :to="{ name: 'catalogo' }" class="btn-primary">Ver el catálogo</RouterLink>
      </div>

      <ul v-else class="flex-1 divide-y divide-slate-100 overflow-y-auto">
        <li v-for="it in carrito.items" :key="it.codigo" class="flex gap-3 p-4">
          <RouterLink :to="{ name: 'producto', params: { codigo: it.codigo } }" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-slate-100 bg-white">
            <img v-if="it.producto?.imagen" :src="it.producto.imagen" alt="" class="h-full w-full object-contain" />
            <div v-else class="flex h-full items-center justify-center text-slate-300"><AppIcon name="box" /></div>
          </RouterLink>
          <div class="min-w-0 flex-1">
            <p class="line-clamp-2 text-sm font-semibold">{{ it.producto?.nombre ?? it.codigo }}</p>
            <p class="text-xs text-slate-400">Cód. {{ it.codigo }}</p>
            <div class="mt-2 flex items-center justify-between gap-2">
              <QtyStepper :model-value="it.cantidad" chico @update:model-value="carrito.fijar(it.codigo, $event)" />
              <span v-if="sitio.verPrecios && it.producto?.precio != null" class="text-sm font-bold">{{ moneda(it.producto.precio * it.cantidad) }}</span>
            </div>
          </div>
        </li>
      </ul>

      <div v-if="carrito.items.length" class="border-t border-slate-100 p-4 pb-safe">
        <div v-if="sitio.verPrecios" class="mb-3 flex items-baseline justify-between">
          <span class="text-slate-500">Total estimado</span>
          <span class="text-xl font-extrabold">{{ moneda(carrito.total) }}</span>
        </div>
        <RouterLink :to="{ name: 'pedido' }" class="btn-primary w-full py-3 text-base">
          {{ sitio.logueado ? 'Revisar y enviar pedido' : 'Continuar' }}<AppIcon name="arrow-right" :size="18" />
        </RouterLink>
      </div>
    </aside>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform .25s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>
