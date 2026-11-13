<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError } from '@/api'
import { useCarrito, useSitio, useUi } from '@/stores'
import { linkWhatsapp, moneda, parrafos, volarAlCarrito } from '@/utils'
import type { ProductoDetalle } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import Galeria from '@/components/Galeria.vue'
import Precio from '@/components/Precio.vue'
import ProductGrid from '@/components/ProductGrid.vue'
import QtyStepper from '@/components/QtyStepper.vue'
import IconWhatsapp from '@/components/IconWhatsapp.vue'

const route = useRoute()
const sitio = useSitio()
const carrito = useCarrito()
const ui = useUi()

const p = ref<ProductoDetalle | null>(null)
const cargando = ref(true)
const error = ref('')
const cantidad = ref(1)
const zonaFoto = ref<HTMLElement | null>(null)
const puedeAgregar = computed(() => sitio.puedeAgregar && !!p.value && (p.value.precio !== null || !sitio.verPrecios))

const codigo = computed(() => String(route.params.codigo ?? ''))
const enCarrito = computed(() => carrito.cantidadDe(codigo.value))
const sinStock = computed(() => p.value?.stock.disponible === false)
const consulta = computed(() => p.value && sitio.whatsapp
  ? linkWhatsapp(sitio.whatsapp, `Hola! Quería consultar por: ${p.value.nombre} (cód. ${p.value.codigo})`) : '')

async function cargar(): Promise<void> {
  if (!codigo.value) return
  cargando.value = true
  error.value = ''
  try {
    p.value = await api.get<ProductoDetalle>('/productos/detalle', { c: codigo.value })
    cantidad.value = Math.max(1, p.value.bulto)
    document.title = `${p.value.nombre} · ${sitio.sitio?.nombre ?? ''}`
  } catch (e) {
    error.value = (e as ApiError).status === 404 ? 'Este producto no está disponible.' : (e as ApiError).message
  } finally {
    cargando.value = false
  }
}
watch(codigo, cargar, { immediate: true })

function agregar(): void {
  if (!p.value || cantidad.value < 1) return
  volarAlCarrito(zonaFoto.value?.querySelector('img'))
  carrito.agregar(p.value, cantidad.value)
  ui.pulsoCarrito++
  ui.avisar(`Agregaste ${cantidad.value} a tu pedido`)
}
async function compartir(): Promise<void> {
  const url = window.location.href
  if (navigator.share) { try { await navigator.share({ title: p.value?.nombre, url }) } catch { /* cancelado */ } return }
  await navigator.clipboard?.writeText(url)
  ui.avisar('Link copiado', 'info')
}
</script>

<template>
  <div class="container-page py-6">
    <div v-if="cargando && !p" class="grid gap-8 md:grid-cols-2">
      <div class="skeleton aspect-square" />
      <div class="space-y-4"><div class="skeleton h-4 w-1/4" /><div class="skeleton h-8 w-3/4" /><div class="skeleton h-10 w-1/3" /><div class="skeleton h-24 w-full" /></div>
    </div>

    <div v-else-if="error" class="card mx-auto max-w-lg p-8 text-center">
      <AppIcon name="box" :size="40" class="mx-auto text-slate-300" />
      <p class="mt-3 font-semibold">{{ error }}</p>
      <RouterLink :to="{ name: 'catalogo' }" class="btn-primary mt-4">Ver el catálogo</RouterLink>
    </div>

    <template v-else-if="p">
      <nav class="mb-4 flex flex-wrap items-center gap-1 text-xs text-slate-400">
        <RouterLink to="/" class="hover:text-brand-600">Inicio</RouterLink><AppIcon name="chevron-right" :size="12" />
        <RouterLink :to="{ name: 'catalogo' }" class="hover:text-brand-600">Catálogo</RouterLink>
        <template v-if="p.rubro"><AppIcon name="chevron-right" :size="12" /><RouterLink :to="{ name: 'catalogo', query: { rubro: p.rubro } }" class="hover:text-brand-600">{{ p.rubro }}</RouterLink></template>
        <template v-if="p.familia"><AppIcon name="chevron-right" :size="12" /><RouterLink :to="{ name: 'catalogo', query: { rubro: p.rubro ?? undefined, familia: String(p.familia.id) } }" class="hover:text-brand-600">{{ p.familia.nombre }}</RouterLink></template>
      </nav>

      <div class="grid gap-6 md:grid-cols-2 lg:gap-10">
        <div ref="zonaFoto"><Galeria :fotos="p.fotos" :alt="p.nombre" /></div>

        <div>
          <div class="flex items-start justify-between gap-3">
            <div>
              <p v-if="p.marca" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ p.marca.nombre }}</p>
              <h1 class="mt-1 text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl">{{ p.nombre }}</h1>
              <p class="mt-1 text-sm text-slate-500">Código: <b class="text-slate-700">{{ p.codigo }}</b></p>
            </div>
            <button type="button" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-brand-600" aria-label="Compartir" @click="compartir">
              <AppIcon name="arrow-right" class="-rotate-45" />
            </button>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span v-if="p.nuevo" class="badge bg-accent-500 text-white">Nuevo</span>
            <span v-if="p.stock.disponible === true" class="badge bg-emerald-100 text-emerald-800">Disponible{{ p.stock.cantidad !== undefined ? ` · ${p.stock.cantidad} u.` : '' }}</span>
            <span v-else-if="sinStock" class="badge bg-slate-200 text-slate-700">Sin stock</span>
            <span v-if="p.bulto > 1" class="badge bg-brand-50 text-brand-700">Bulto x {{ p.bulto }}</span>
          </div>

          <div class="card mt-5 p-5">
            <Precio :producto="p" grande />
            <p v-if="sitio.verPrecios && p.precio !== null && !sitio.logueado" class="mt-1 text-xs text-slate-500">Precio de lista. Ingresá para ver tu precio.</p>

            <div v-if="puedeAgregar" class="mt-4 flex flex-wrap items-center gap-3">
              <QtyStepper v-model="cantidad" :min="1" />
              <button type="button" class="btn-primary flex-1 py-3 text-base" @click="agregar"><AppIcon name="cart" />Agregar al pedido</button>
              <p v-if="enCarrito" class="w-full text-sm text-emerald-700">Ya tenés {{ enCarrito }} en tu pedido · <button type="button" class="font-semibold underline" @click="ui.carritoAbierto = true">ver pedido</button></p>
              <p v-if="sinStock" class="w-full text-xs text-amber-700">Sin stock por el momento: podés pedirlo igual y te avisamos cuándo llega.</p>
            </div>
            <p v-if="puedeAgregar && !sitio.logueado" class="mt-2 text-xs text-slate-500">Armá tu pedido: para enviarlo te pedimos que ingreses.</p>
            <div v-else-if="!sitio.logueado && !puedeAgregar" class="mt-4 flex flex-col gap-2 sm:flex-row">
              <RouterLink :to="{ name: 'login', query: { volver: route.fullPath } }" class="btn-primary flex-1 py-3">Ingresar para hacer pedidos</RouterLink>
            </div>
            <a v-if="consulta" :href="consulta" target="_blank" rel="noopener" class="btn-whatsapp mt-3 w-full py-3"><IconWhatsapp />Consultar por WhatsApp</a>
          </div>

          <section v-if="p.descripcion" class="mt-6">
            <h2 class="mb-2 text-lg font-bold">Descripción</h2>
            <p v-for="(par, i) in parrafos(p.descripcion)" :key="i" class="mb-3 whitespace-pre-line leading-relaxed text-slate-600">{{ par }}</p>
          </section>

          <section v-if="p.caracteristicas.length" class="mt-6">
            <h2 class="mb-2 text-lg font-bold">Características</h2>
            <dl class="overflow-hidden rounded-2xl border border-slate-100">
              <div v-for="(c, i) in p.caracteristicas" :key="c.nombre" class="grid grid-cols-2 gap-3 px-4 py-2.5 text-sm" :class="i % 2 ? 'bg-white' : 'bg-slate-50'">
                <dt class="text-slate-500">{{ c.nombre }}</dt><dd class="font-semibold text-slate-800">{{ c.valor }}</dd>
              </div>
            </dl>
          </section>

          <p v-if="sitio.verPrecios && p.precio !== null && enCarrito" class="mt-4 text-sm text-slate-500">Subtotal en tu pedido: <b>{{ moneda(p.precio * enCarrito) }}</b></p>
        </div>
      </div>

      <section v-if="p.relacionados.length" class="mt-12">
        <h2 class="section-title mb-4">También te puede interesar</h2>
        <ProductGrid :productos="p.relacionados.slice(0, 8)" />
      </section>
    </template>
  </div>
</template>
