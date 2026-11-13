<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api, ApiError } from '@/api'
import { useCarrito, useSitio, useUi } from '@/stores'
import { parrafos, volarAlCarrito } from '@/utils'
import type { ProductoDetalle } from '@/types'
import AppIcon from './AppIcon.vue'
import Galeria from './Galeria.vue'
import Precio from './Precio.vue'
import QtyStepper from './QtyStepper.vue'

const ui = useUi()
const sitio = useSitio()
const carrito = useCarrito()
const p = ref<ProductoDetalle | null>(null)
const error = ref('')
const cantidad = ref(1)
const zonaFoto = ref<HTMLElement | null>(null)
const enCarrito = computed(() => (p.value ? carrito.cantidadDe(p.value.codigo) : 0))
const puedeAgregar = computed(() => sitio.puedeAgregar && p.value && (p.value.precio !== null || !sitio.verPrecios))

watch(() => ui.vistaRapida, async (codigo) => {
  p.value = null
  error.value = ''
  if (!codigo) return
  try {
    p.value = await api.get<ProductoDetalle>('/productos/detalle', { c: codigo })
    cantidad.value = Math.max(1, p.value.bulto)
  } catch (e) { error.value = (e as ApiError).message }
})

function agregar(): void {
  if (!p.value) return
  volarAlCarrito(zonaFoto.value?.querySelector('img'))
  carrito.agregar(p.value, cantidad.value)
  ui.pulsoCarrito++
  ui.avisar(`Agregaste ${cantidad.value} a tu pedido`)
  setTimeout(() => { ui.vistaRapida = null }, 450)
}
</script>

<template>
  <Transition name="fondo"><div v-if="ui.vistaRapida" class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm" @click="ui.vistaRapida = null" /></Transition>
  <Transition name="hoja">
    <div v-if="ui.vistaRapida" class="fixed inset-x-0 bottom-0 z-[61] max-h-[92vh] overflow-y-auto rounded-t-3xl bg-white shadow-2xl sm:inset-0 sm:m-auto sm:h-fit sm:max-h-[88vh] sm:max-w-4xl sm:rounded-3xl"
         role="dialog" aria-modal="true">
      <div class="sticky top-0 z-10 flex justify-center bg-white/90 pt-2 backdrop-blur sm:hidden"><span class="h-1.5 w-12 rounded-full bg-slate-200" /></div>
      <button type="button" class="absolute right-3 top-3 z-20 rounded-full bg-white/90 p-2 shadow hover:bg-slate-100" aria-label="Cerrar" @click="ui.vistaRapida = null"><AppIcon name="close" /></button>

      <div v-if="error" class="p-10 text-center text-slate-500">{{ error }}</div>
      <div v-else-if="!p" class="grid gap-6 p-6 sm:grid-cols-2"><div class="skeleton aspect-square" /><div class="space-y-3"><div class="skeleton h-6 w-3/4" /><div class="skeleton h-10 w-1/2" /><div class="skeleton h-24" /></div></div>
      <div v-else class="grid gap-6 p-5 sm:grid-cols-2 sm:p-7">
        <div ref="zonaFoto"><Galeria :fotos="p.fotos" :alt="p.nombre" /></div>
        <div class="flex flex-col">
          <p v-if="p.marca" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ p.marca.nombre }}</p>
          <h2 class="mt-1 text-xl font-extrabold leading-tight sm:text-2xl">{{ p.nombre }}</h2>
          <p class="mt-1 text-sm text-slate-500">Código: <b>{{ p.codigo }}</b><template v-if="p.rubro"> · {{ p.rubro }}</template></p>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span v-if="p.nuevo" class="badge bg-accent-500 text-white">Nuevo</span>
            <span v-if="p.stock.disponible === true" class="badge bg-emerald-100 text-emerald-800">Disponible{{ p.stock.cantidad !== undefined ? ` · ${p.stock.cantidad} u.` : '' }}</span>
            <span v-else-if="p.stock.disponible === false" class="badge bg-slate-200 text-slate-700">Sin stock</span>
            <span v-if="p.bulto > 1" class="badge bg-brand-50 text-brand-700">Bulto x {{ p.bulto }}</span>
          </div>
          <div class="mt-4"><Precio :producto="p" grande /></div>

          <dl v-if="p.caracteristicas.length" class="mt-4 overflow-hidden rounded-xl border border-slate-100 text-sm">
            <div v-for="(c, i) in p.caracteristicas.slice(0, 6)" :key="c.nombre" class="grid grid-cols-2 gap-2 px-3 py-1.5" :class="i % 2 ? '' : 'bg-slate-50'">
              <dt class="text-slate-500">{{ c.nombre }}</dt><dd class="font-semibold">{{ c.valor }}</dd>
            </div>
          </dl>
          <p v-else-if="p.descripcion" class="mt-4 line-clamp-4 whitespace-pre-line text-sm text-slate-600">{{ parrafos(p.descripcion)[0] }}</p>

          <div class="mt-auto pt-5">
            <div v-if="puedeAgregar" class="flex items-center gap-3">
              <QtyStepper v-model="cantidad" :min="1" />
              <button type="button" class="btn-primary flex-1 py-3 text-base" @click="agregar"><AppIcon name="cart" />Agregar</button>
            </div>
            <p v-if="enCarrito" class="mt-2 text-sm text-emerald-700">Ya tenés {{ enCarrito }} en tu pedido</p>
            <RouterLink :to="{ name: 'producto', params: { codigo: p.codigo } }" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:underline" @click="ui.vistaRapida = null">
              Ver ficha completa<AppIcon name="arrow-right" :size="16" />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.fondo-enter-active, .fondo-leave-active { transition: opacity .25s; }
.fondo-enter-from, .fondo-leave-to { opacity: 0; }
.hoja-enter-active, .hoja-leave-active { transition: transform .35s cubic-bezier(.22,1,.36,1), opacity .25s; }
.hoja-enter-from, .hoja-leave-to { transform: translateY(40px) scale(.98); opacity: 0; }
@media (max-width: 639px) { .hoja-enter-from, .hoja-leave-to { transform: translateY(100%); opacity: 1; } }
</style>
