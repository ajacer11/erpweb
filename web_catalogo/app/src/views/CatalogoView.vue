<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import { api, ApiError } from '@/api'
import { useSitio } from '@/stores'
import type { Filtros, Pagina, Producto } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import ProductGrid from '@/components/ProductGrid.vue'
import Paginacion from '@/components/Paginacion.vue'
import RubroTile from '@/components/RubroTile.vue'

const route = useRoute()
const router = useRouter()
const sitio = useSitio()

const resultado = ref<Pagina<Producto> | null>(null)
const filtros = ref<Filtros | null>(null)
const cargando = ref(true)
const error = ref('')
const filtrosAbiertos = ref(false)

const q = computed(() => String(route.query.q ?? ''))
const rubro = computed(() => String(route.query.rubro ?? ''))
const familia = computed(() => String(route.query.familia ?? ''))
const marca = computed(() => String(route.query.marca ?? ''))
const orden = computed(() => String(route.query.orden ?? ''))
const nuevos = computed(() => route.query.nuevos === '1')
const pagina = computed(() => Math.max(1, parseInt(String(route.query.pagina ?? '1'), 10) || 1))
/** Atributos: ?atr_3=Carbono */
const atributos = computed(() => {
  const out: Record<string, string> = {}
  for (const [k, v] of Object.entries(route.query)) if (k.startsWith('atr_') && v) out[k.slice(4)] = String(v)
  return out
})

const titulo = computed(() => {
  if (q.value) return `Resultados para "${q.value}"`
  if (nuevos.value) return 'Novedades'
  return rubro.value || nombreFamilia.value || 'Catálogo'
})
const nombreFamilia = computed(() => filtros.value?.familias.find((f) => String(f.id) === familia.value)?.nombre)
const nombreMarca = computed(() => filtros.value?.marcas.find((m) => String(m.id) === marca.value)?.nombre)
const cantFiltros = computed(() => [rubro.value, familia.value, marca.value, nuevos.value ? '1' : ''].filter(Boolean).length + Object.keys(atributos.value).length)

function actualizar(cambios: Record<string, string | null>, resetPagina = true): void {
  const query: LocationQueryRaw = { ...route.query, ...cambios }
  if (resetPagina) delete query.pagina
  for (const k of Object.keys(query)) if (query[k] === null || query[k] === '') delete query[k]
  router.push({ name: 'catalogo', query })
}
function limpiar(): void { router.push({ name: 'catalogo', query: q.value ? { q: q.value } : {} }) }

async function cargar(): Promise<void> {
  if (!sitio.verCatalogo) { cargando.value = false; return }
  cargando.value = true
  error.value = ''
  const params = { q: q.value, rubro: rubro.value, familia: familia.value, marca: marca.value, orden: orden.value, nuevos: nuevos.value ? 1 : '', pagina: pagina.value, por_pagina: 24, atr: atributos.value }
  try {
    const [r, f] = await Promise.all([
      api.get<Pagina<Producto>>('/productos', params),
      api.get<Filtros>('/filtros', { familia: familia.value, rubro: rubro.value }),
    ])
    resultado.value = r
    filtros.value = f
    if (familia.value) setTimeout(() => document.querySelector('.rubro-tile.activo')?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }), 50)
  } catch (e) {
    error.value = (e as ApiError).message
  } finally {
    cargando.value = false
  }
}
watch(() => route.query, cargar, { immediate: true, deep: true })
watch(pagina, () => window.scrollTo({ top: 0, behavior: 'smooth' }))
</script>

<template>
  <div class="container-page py-6">
    <div v-if="!sitio.verCatalogo" class="card mx-auto max-w-lg p-8 text-center">
      <AppIcon name="lock" :size="40" class="mx-auto text-brand-600" />
      <p class="mt-3 text-lg font-bold">El catálogo es exclusivo para clientes</p>
      <RouterLink :to="{ name: 'login', query: { volver: route.fullPath } }" class="btn-primary mt-4">Ingresar</RouterLink>
    </div>

    <template v-else>
      <nav class="mb-2 flex items-center gap-1 text-xs text-slate-400">
        <RouterLink to="/" class="hover:text-brand-600">Inicio</RouterLink><AppIcon name="chevron-right" :size="12" />
        <RouterLink :to="{ name: 'catalogo' }" class="hover:text-brand-600">Catálogo</RouterLink>
        <template v-if="familia"><AppIcon name="chevron-right" :size="12" />
          <RouterLink v-if="rubro" :to="{ name: 'catalogo', query: { familia } }" class="hover:text-brand-600">{{ nombreFamilia || 'Familia' }}</RouterLink>
          <span v-else class="text-slate-600">{{ nombreFamilia }}</span></template>
        <template v-if="rubro"><AppIcon name="chevron-right" :size="12" /><span class="text-slate-600">{{ rubro }}</span></template>
      </nav>
      <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ titulo }}</h1>
          <p v-if="resultado" class="text-sm text-slate-500">{{ resultado.total }} {{ resultado.total === 1 ? 'producto' : 'productos' }}</p>
        </div>
        <div class="flex w-full items-center gap-2 sm:w-auto">
          <button type="button" class="btn-secondary flex-1 lg:hidden" @click="filtrosAbiertos = true">
            <AppIcon name="filter" :size="16" />Filtrar<span v-if="cantFiltros" class="rounded-full bg-brand-600 px-1.5 text-xs text-white">{{ cantFiltros }}</span>
          </button>
          <select :value="orden" class="input w-auto flex-1 py-2 sm:flex-none" aria-label="Ordenar" @change="actualizar({ orden: ($event.target as HTMLSelectElement).value })">
            <option value="">Ordenar: recomendado</option>
            <option v-if="sitio.verPrecios" value="precio_asc">Menor precio</option>
            <option v-if="sitio.verPrecios" value="precio_desc">Mayor precio</option>
            <option value="nombre">Nombre A-Z</option>
            <option value="nuevos">Más nuevos</option>
            <option value="codigo">Código</option>
          </select>
          <RouterLink :to="{ name: 'imprimir', query: route.query }" class="btn-secondary hidden px-3 sm:inline-flex" title="Lista para imprimir"><AppIcon name="print" :size="18" /></RouterLink>
        </div>
      </div>

      <!-- Familias con íconos animados (primer nivel) -->
      <div v-if="filtros?.familias.length" class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-2 pt-1 scrollbar-hide sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
        <RubroTile v-for="f in filtros.familias" :key="f.id" :nombre="f.nombre" :familia="f.id" chico :activo="String(f.id) === familia" class="shrink-0" />
      </div>

      <!-- Filtros activos -->
      <div v-if="cantFiltros" class="mb-4 flex flex-wrap gap-2">
        <button v-if="familia" type="button" class="chip chip-active" @click="actualizar({ familia: null, rubro: null })">{{ nombreFamilia || 'Familia' }}<AppIcon name="close" :size="14" /></button>
        <button v-if="rubro" type="button" class="chip chip-active" @click="actualizar({ rubro: null })">{{ rubro }}<AppIcon name="close" :size="14" /></button>
        <button v-if="marca" type="button" class="chip chip-active" @click="actualizar({ marca: null })">{{ nombreMarca || 'Marca' }}<AppIcon name="close" :size="14" /></button>
        <button v-if="nuevos" type="button" class="chip chip-active" @click="actualizar({ nuevos: null })">Novedades<AppIcon name="close" :size="14" /></button>
        <button v-for="(v, id) in atributos" :key="id" type="button" class="chip chip-active" @click="actualizar({ ['atr_' + id]: null })">{{ v }}<AppIcon name="close" :size="14" /></button>
        <button type="button" class="chip" @click="limpiar">Limpiar filtros</button>
      </div>

      <div class="flex gap-6">
        <!-- Panel de filtros: lateral en escritorio, hoja inferior en celular -->
        <Transition name="fade"><div v-if="filtrosAbiertos" class="fixed inset-0 z-50 bg-slate-900/50 lg:hidden" @click="filtrosAbiertos = false" /></Transition>
        <aside class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-white p-5 pb-safe shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:block lg:max-h-none lg:w-64 lg:shrink-0 lg:translate-y-0 lg:rounded-none lg:bg-transparent lg:p-0 lg:shadow-none"
               :class="filtrosAbiertos ? 'translate-y-0' : 'translate-y-full'">
          <div class="mb-4 flex items-center justify-between lg:hidden">
            <p class="text-lg font-bold">Filtrar</p>
            <button type="button" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Cerrar" @click="filtrosAbiertos = false"><AppIcon name="close" /></button>
          </div>
          <div v-if="filtros" class="space-y-6" @click="(e) => { if ((e.target as HTMLElement).closest('button[data-f]')) filtrosAbiertos = false }">
            <div v-if="filtros.familias.length > 1 || familia">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Familia</p>
              <div class="flex flex-wrap gap-2 lg:max-h-72 lg:flex-col lg:flex-nowrap lg:gap-0.5 lg:overflow-y-auto">
                <button v-for="f in filtros.familias" :key="f.id" data-f type="button" class="chip lg:flex lg:justify-between lg:rounded-lg lg:border-0 lg:bg-transparent lg:px-2 lg:py-1.5 lg:text-left lg:hover:bg-slate-100"
                        :class="{ 'chip-active lg:!bg-brand-600': String(f.id) === familia }" @click="actualizar({ familia: String(f.id) === familia ? null : String(f.id), rubro: null })">
                  <span>{{ f.nombre }}</span><span class="text-xs opacity-60">{{ f.cantidad }}</span>
                </button>
              </div>
            </div>
            <div v-if="filtros.rubros.length > 1 || rubro">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Rubro</p>
              <div class="flex flex-wrap gap-2 lg:max-h-96 lg:flex-col lg:flex-nowrap lg:gap-0.5 lg:overflow-y-auto">
                <button v-for="r in filtros.rubros" :key="r.nombre" data-f type="button" class="chip lg:flex lg:justify-between lg:rounded-lg lg:border-0 lg:bg-transparent lg:px-2 lg:py-1.5 lg:text-left lg:hover:bg-slate-100"
                        :class="{ 'chip-active lg:!bg-brand-600': r.nombre === rubro }" @click="actualizar({ rubro: r.nombre === rubro ? null : r.nombre })">
                  <span>{{ r.nombre }}</span><span class="text-xs opacity-60">{{ r.cantidad }}</span>
                </button>
              </div>
            </div>
            <div v-if="filtros.marcas.length > 1 || marca">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Marca</p>
              <div class="flex flex-wrap gap-2 lg:max-h-72 lg:flex-col lg:flex-nowrap lg:gap-0.5 lg:overflow-y-auto">
                <button v-for="m in filtros.marcas" :key="m.id" data-f type="button" class="chip lg:flex lg:justify-between lg:rounded-lg lg:border-0 lg:bg-transparent lg:px-2 lg:py-1.5 lg:text-left lg:hover:bg-slate-100"
                        :class="{ 'chip-active lg:!bg-brand-600': String(m.id) === marca }" @click="actualizar({ marca: String(m.id) === marca ? null : String(m.id) })">
                  <span>{{ m.nombre }}</span><span class="text-xs opacity-60">{{ m.cantidad }}</span>
                </button>
              </div>
            </div>
            <div v-for="a in filtros.atributos" :key="a.id">
              <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">{{ a.nombre }}</p>
              <div class="flex flex-wrap gap-2">
                <button v-for="v in a.valores" :key="v.valor" data-f type="button" class="chip" :class="{ 'chip-active': atributos[a.id] === v.valor }"
                        @click="actualizar({ ['atr_' + a.id]: atributos[a.id] === v.valor ? null : v.valor })">
                  {{ v.valor }}{{ a.unidad ? ' ' + a.unidad : '' }}<span class="text-xs opacity-60">{{ v.cantidad }}</span>
                </button>
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" class="h-4 w-4 accent-[var(--c-primario)]" :checked="nuevos" @change="actualizar({ nuevos: nuevos ? null : '1' }); filtrosAbiertos = false" />Solo novedades
            </label>
          </div>
        </aside>

        <div class="min-w-0 flex-1">
          <div v-if="error" class="card p-6 text-center text-red-600">{{ error }}</div>
          <template v-else>
            <ProductGrid :productos="resultado?.items ?? []" :cargando="cargando && !resultado" />
            <div v-if="!cargando && resultado && !resultado.items.length" class="card p-10 text-center text-slate-500">
              <AppIcon name="search" :size="40" class="mx-auto text-slate-300" />
              <p class="mt-3 font-semibold">No encontramos productos</p>
              <p class="text-sm">Probá con otras palabras o sacá algún filtro.</p>
              <button v-if="cantFiltros || q" type="button" class="btn-secondary mt-4" @click="router.push({ name: 'catalogo' })">Ver todo el catálogo</button>
            </div>
            <div class="mt-8" :class="{ 'opacity-50': cargando }">
              <Paginacion v-if="resultado" :pagina="resultado.pagina" :paginas="resultado.paginas" @ir="(p) => actualizar({ pagina: String(p) }, false)" />
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
