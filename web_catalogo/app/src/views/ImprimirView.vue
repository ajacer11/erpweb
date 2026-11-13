<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError } from '@/api'
import { useSitio } from '@/stores'
import { moneda } from '@/utils'
import type { Producto } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const sitio = useSitio()
const productos = ref<Producto[]>([])
const cargando = ref(true)
const error = ref('')
const conFotos = ref(false)
const hoy = new Date().toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' })
const imprimir = () => window.print()
const conIva = computed(() => sitio.sitio?.catalogo.precioConIva !== false)

const porRubro = computed(() => {
  const m = new Map<string, Producto[]>()
  for (const p of productos.value) {
    const r = p.rubro || 'Otros'
    if (!m.has(r)) m.set(r, [])
    m.get(r)!.push(p)
  }
  return [...m.entries()]
})

onMounted(async () => {
  const atr: Record<string, string> = {}
  for (const [k, v] of Object.entries(route.query)) if (k.startsWith('atr_') && v) atr[k.slice(4)] = String(v)
  try {
    const r = await api.get<{ items: Producto[] }>('/productos/imprimir', { q: route.query.q, rubro: route.query.rubro, familia: route.query.familia, marca: route.query.marca, nuevos: route.query.nuevos, orden: route.query.orden, atr })
    productos.value = r.items
  } catch (e) { error.value = (e as ApiError).message } finally { cargando.value = false }
})
</script>

<template>
  <div class="container-page max-w-5xl py-6">
    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
      <RouterLink :to="{ name: 'catalogo', query: route.query }" class="btn-ghost -ml-3"><AppIcon name="arrow-left" :size="18" />Volver al catálogo</RouterLink>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm"><input v-model="conFotos" type="checkbox" class="h-4 w-4" />Con fotos</label>
        <button type="button" class="btn-primary" :disabled="cargando" @click="imprimir"><AppIcon name="print" :size="18" />Imprimir / PDF</button>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-card print:p-0 print:shadow-none">
      <header class="mb-6 flex items-center justify-between gap-4 border-b-2 border-slate-800 pb-4">
        <div>
          <img v-if="sitio.sitio?.logo" :src="sitio.sitio.logo" :alt="sitio.sitio.nombre" class="mb-1 h-12 object-contain" />
          <p v-else class="text-2xl font-extrabold">{{ sitio.sitio?.nombre }}</p>
          <p class="text-sm text-slate-600">{{ sitio.sitio?.slogan }}</p>
        </div>
        <div class="text-right text-sm text-slate-600">
          <p class="font-bold text-slate-900">Lista de productos</p>
          <p>{{ hoy }}</p>
          <p v-if="sitio.logueado">{{ sitio.cliente?.razon }}</p>
          <p v-if="sitio.verPrecios">{{ conIva ? 'Precios finales con IVA' : 'Precios netos + IVA' }}</p>
          <p v-if="sitio.sitio?.contacto.whatsapp">WhatsApp: +{{ sitio.sitio.contacto.whatsapp }}</p>
        </div>
      </header>

      <p v-if="cargando" class="py-10 text-center text-slate-500">Armando la lista...</p>
      <p v-else-if="error" class="py-10 text-center text-red-600">{{ error }}</p>
      <template v-else>
        <section v-for="[rubro, items] in porRubro" :key="rubro" class="mb-6 break-inside-avoid-page">
          <h2 class="mb-2 bg-slate-800 px-3 py-1.5 text-sm font-bold uppercase tracking-wide text-white print:bg-slate-800">{{ rubro }}</h2>
          <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-300 text-left text-xs uppercase text-slate-500">
              <th v-if="conFotos" class="w-16 py-1"></th><th class="w-28 py-1">Código</th><th class="py-1">Descripción</th><th v-if="sitio.verPrecios" class="w-32 py-1 text-right">Precio</th>
            </tr></thead>
            <tbody>
              <tr v-for="p in items" :key="p.codigo" class="break-inside-avoid border-b border-slate-100">
                <td v-if="conFotos" class="py-1"><img v-if="p.imagen" :src="p.imagen" alt="" class="h-12 w-12 object-contain" /></td>
                <td class="py-1.5 pr-2 font-mono text-xs text-slate-600">{{ p.codigo }}</td>
                <td class="py-1.5 pr-2">{{ p.nombre }}<span v-if="p.marca" class="text-slate-400"> · {{ p.marca.nombre }}</span></td>
                <td v-if="sitio.verPrecios" class="py-1.5 text-right font-semibold">{{ p.precio === null ? 'Consultar' : moneda(conIva ? p.precio : p.precioNeto) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
        <p class="mt-6 text-center text-xs text-slate-400">{{ productos.length }} productos · Precios sujetos a modificación sin previo aviso.</p>
      </template>
    </div>
  </div>
</template>
