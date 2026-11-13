<script setup lang="ts">
import { computed, ref } from 'vue'
import { useCarrito, useSitio, useUi } from '@/stores'
import { volarAlCarrito } from '@/utils'
import type { Producto } from '@/types'
import AppIcon from './AppIcon.vue'
import Precio from './Precio.vue'
import QtyStepper from './QtyStepper.vue'

const props = defineProps<{ producto: Producto }>()
const sitio = useSitio()
const carrito = useCarrito()
const ui = useUi()
const foto = ref<HTMLImageElement | null>(null)

const enCarrito = computed({
  get: () => carrito.cantidadDe(props.producto.codigo),
  set: (n: number) => carrito.fijar(props.producto.codigo, n),
})
const sinStock = computed(() => props.producto.stock.disponible === false)
const link = computed(() => ({ name: 'producto', params: { codigo: props.producto.codigo } }))
// Se puede agregar si la empresa lo permite (logueado o pedido sin ingresar) y el producto tiene precio
// (si los precios no se muestran al público, se agrega igual: el precio se ve al ingresar).
const puedeAgregar = computed(() => sitio.puedeAgregar && (props.producto.precio !== null || !sitio.verPrecios))
const vistaRapida = computed(() => !!sitio.diseno?.vistaRapida)

function agregar(): void {
  volarAlCarrito(foto.value)
  carrito.agregar(props.producto, props.producto.bulto > 1 ? props.producto.bulto : 1)
  ui.pulsoCarrito++
  ui.avisar('Agregado a tu pedido')
}
</script>

<template>
  <article class="tarjeta group card relative flex h-full flex-col overflow-hidden">
    <div class="relative">
      <RouterLink :to="link" class="block aspect-square overflow-hidden bg-white">
        <img v-if="producto.imagen" ref="foto" :src="producto.imagen" :alt="producto.nombre" loading="lazy"
             class="h-full w-full object-contain p-3 transition duration-500 group-hover:scale-110" />
        <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 text-slate-300"><AppIcon name="box" :size="48" :stroke-width="1.5" /></div>
      </RouterLink>
      <div class="pointer-events-none absolute left-2 top-2 flex flex-col items-start gap-1">
        <span v-if="producto.nuevo" class="badge nuevo bg-accent-500 text-white shadow">Nuevo</span>
        <span v-if="sinStock" class="badge bg-slate-800/85 text-white">Sin stock</span>
      </div>
      <button v-if="vistaRapida" type="button" class="vista absolute right-2 top-2 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-md hover:bg-brand-600 hover:text-white"
              title="Vista rápida" aria-label="Vista rápida" @click="ui.vistaRapida = producto.codigo">
        <AppIcon name="zoom" :size="18" />
      </button>
    </div>

    <div class="flex flex-1 flex-col gap-1 border-t border-slate-50 p-3 sm:p-4">
      <p v-if="producto.marca" class="truncate text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ producto.marca.nombre }}</p>
      <RouterLink :to="link" class="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 transition hover:text-brand-600">{{ producto.nombre }}</RouterLink>
      <p class="text-[11px] text-slate-400">Cód. {{ producto.codigo }}</p>
      <div class="mt-auto pt-1"><Precio :producto="producto" /></div>
      <p v-if="producto.stock.cantidad !== undefined && !sinStock" class="text-xs text-emerald-700">Stock: {{ producto.stock.cantidad }}</p>

      <template v-if="puedeAgregar">
        <QtyStepper v-if="enCarrito > 0" v-model="enCarrito" chico class="mt-2 w-full justify-between" />
        <button v-else type="button" class="agregar btn-primary mt-2 w-full px-3" @click="agregar">
          <AppIcon name="cart" :size="16" />Agregar
        </button>
      </template>
    </div>
  </article>
</template>

<style scoped>
.tarjeta { transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s; }
.tarjeta:hover { transform: translateY(-4px); box-shadow: 0 2px 4px rgb(15 23 42 / .06), 0 18px 40px -10px rgb(15 23 42 / .22); }
.vista { opacity: 0; transform: scale(.8); transition: all .2s; }
.tarjeta:hover .vista, .vista:focus-visible { opacity: 1; transform: scale(1); }
@media (hover: none) { .vista { opacity: 1; transform: none; } }
.nuevo { animation: latido 2.4s ease-in-out infinite; }
@keyframes latido { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
.agregar { position: relative; overflow: hidden; }
.agregar::after { content: ''; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 30%, rgb(255 255 255 / .35) 50%, transparent 70%); transform: translateX(-100%); }
.tarjeta:hover .agregar::after { transform: translateX(100%); transition: transform .7s ease; }
:global(.anim-ninguna) .nuevo { animation: none; }
:global(.anim-ninguna) .tarjeta:hover { transform: none; }
</style>
