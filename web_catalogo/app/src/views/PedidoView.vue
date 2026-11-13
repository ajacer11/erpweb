<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, ApiError } from '@/api'
import { useCarrito, useSitio, useUi } from '@/stores'
import { moneda } from '@/utils'
import AppIcon from '@/components/AppIcon.vue'
import QtyStepper from '@/components/QtyStepper.vue'

const carrito = useCarrito()
const sitio = useSitio()
const ui = useUi()

const observaciones = ref('')
const enviando = ref(false)
const error = ref('')
const enviado = ref<{ numero: number; total: number; mensaje: string } | null>(null)

const minimo = computed(() => sitio.sitio?.pedidos.montoMinimo ?? 0)
const faltaMinimo = computed(() => minimo.value > 0 && carrito.total < minimo.value)
const puedeEnviar = computed(() => sitio.puedePedir && carrito.items.length > 0 && !faltaMinimo.value && !carrito.sinPrecio.length && !enviando.value)

onMounted(async () => {
  try {
    const quitados = await carrito.actualizarPrecios()
    if (quitados.length) ui.avisar(`Se quitaron productos que ya no están disponibles:\n${quitados.join('\n')}`, 'info', 6000)
  } catch { /* se reintenta al enviar */ }
})

async function enviar(): Promise<void> {
  if (!puedeEnviar.value) return
  enviando.value = true
  error.value = ''
  try {
    const r = await api.post<{ numero: number; total: number; mensaje: string }>('/cuenta/pedidos', {
      items: carrito.items.map((i) => ({ codigo: i.codigo, cantidad: i.cantidad })),
      observaciones: observaciones.value,
    })
    enviado.value = r
    carrito.vaciar()
    window.scrollTo({ top: 0 })
  } catch (e) {
    error.value = (e as ApiError).message
    if ((e as ApiError).codigo === 'carrito_cambio') await carrito.actualizarPrecios().catch(() => undefined)
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="container-page max-w-4xl py-6">
    <!-- Pedido enviado -->
    <div v-if="enviado" class="card mx-auto max-w-lg animate-fade-up p-8 text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><AppIcon name="check" :size="32" :stroke-width="3" /></div>
      <h1 class="mt-4 text-2xl font-extrabold">¡Pedido enviado!</h1>
      <p class="mt-1 text-slate-500">Número de pedido <b class="text-slate-800">#{{ enviado.numero }}</b> · {{ moneda(enviado.total) }}</p>
      <p class="mt-4 whitespace-pre-line text-slate-600">{{ enviado.mensaje }}</p>
      <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <RouterLink :to="{ name: 'pedido-detalle', params: { numero: enviado.numero } }" class="btn-primary">Ver mi pedido</RouterLink>
        <RouterLink :to="{ name: 'catalogo' }" class="btn-secondary">Seguir comprando</RouterLink>
      </div>
    </div>

    <template v-else>
      <h1 class="mb-4 text-2xl font-extrabold tracking-tight sm:text-3xl">Mi pedido</h1>

      <div v-if="!carrito.items.length" class="card p-10 text-center text-slate-500">
        <AppIcon name="cart" :size="48" :stroke-width="1.5" class="mx-auto text-slate-300" />
        <p class="mt-3 font-semibold">Tu pedido está vacío</p>
        <RouterLink :to="{ name: 'catalogo' }" class="btn-primary mt-4">Ver el catálogo</RouterLink>
      </div>

      <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <ul class="card divide-y divide-slate-100">
          <li v-for="it in carrito.items" :key="it.codigo" class="flex gap-3 p-4">
            <RouterLink :to="{ name: 'producto', params: { codigo: it.codigo } }" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-slate-100 bg-white">
              <img v-if="it.producto?.imagen" :src="it.producto.imagen" alt="" class="h-full w-full object-contain" />
              <div v-else class="flex h-full items-center justify-center text-slate-300"><AppIcon name="box" /></div>
            </RouterLink>
            <div class="min-w-0 flex-1">
              <RouterLink :to="{ name: 'producto', params: { codigo: it.codigo } }" class="line-clamp-2 font-semibold hover:text-brand-600">{{ it.producto?.nombre ?? it.codigo }}</RouterLink>
              <p class="text-xs text-slate-400">Cód. {{ it.codigo }}<template v-if="sitio.verPrecios && it.producto?.precio != null"> · {{ moneda(it.producto.precio) }} c/u</template></p>
              <p v-if="it.producto && it.producto.precio === null" class="text-xs font-semibold text-amber-700">Sin precio: consultanos o quitalo del pedido</p>
              <p v-if="it.producto?.stock.disponible === false" class="text-xs text-amber-700">Sin stock por el momento</p>
              <div class="mt-2 flex items-center justify-between gap-2">
                <QtyStepper :model-value="it.cantidad" chico @update:model-value="carrito.fijar(it.codigo, $event)" />
                <div class="flex items-center gap-2">
                  <span v-if="sitio.verPrecios && it.producto?.precio != null" class="font-bold">{{ moneda(it.producto.precio * it.cantidad) }}</span>
                  <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Quitar" @click="carrito.quitar(it.codigo)"><AppIcon name="trash" :size="18" /></button>
                </div>
              </div>
            </div>
          </li>
        </ul>

        <aside class="lg:sticky lg:top-24 lg:self-start">
          <div class="card p-5">
            <div v-if="sitio.verPrecios" class="flex items-baseline justify-between">
              <span class="text-slate-500">Total estimado</span>
              <span class="text-2xl font-extrabold">{{ moneda(carrito.total) }}</span>
            </div>
            <p v-if="sitio.verPrecios" class="mt-1 text-xs text-slate-400">{{ carrito.unidades }} unidades. Los precios se confirman al facturar.</p>
            <p v-if="faltaMinimo" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">
              El pedido mínimo es de <b>{{ moneda(minimo) }}</b>. Te faltan {{ moneda(minimo - carrito.total) }}.
            </p>

            <template v-if="sitio.logueado">
              <template v-if="sitio.sitio?.pedidos.habilitados">
                <label class="label mt-4" for="obs">Observaciones (opcional)</label>
                <textarea id="obs" v-model="observaciones" rows="3" maxlength="300" class="input" placeholder="Ej: entregar por la tarde, enviar por transporte X..." />
                <p v-if="error" class="mt-3 whitespace-pre-line rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                <button type="button" class="btn-primary mt-4 w-full py-3 text-base" :disabled="!puedeEnviar" @click="enviar">
                  <span v-if="enviando">Enviando...</span><template v-else><AppIcon name="check" />Enviar pedido</template>
                </button>
              </template>
              <p v-else class="mt-4 rounded-xl bg-slate-100 p-3 text-sm text-slate-600">Por ahora no se reciben pedidos desde la web. Contactanos para hacer tu pedido.</p>
            </template>
            <div v-else class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
              <p class="font-semibold">Para enviar el pedido tenés que ingresar.</p>
              <p class="mt-1">Tu pedido queda guardado y vas a ver tus precios.</p>
              <RouterLink :to="{ name: 'login', query: { volver: '/pedido' } }" class="btn-primary mt-3 w-full">Ingresar</RouterLink>
              <RouterLink v-if="sitio.sitio?.registroHabilitado" :to="{ name: 'registro' }" class="mt-2 block text-center font-semibold underline">Quiero ser cliente</RouterLink>
            </div>
            <button type="button" class="mt-3 w-full text-center text-sm text-slate-400 hover:text-red-600" @click="carrito.vaciar()">Vaciar pedido</button>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
