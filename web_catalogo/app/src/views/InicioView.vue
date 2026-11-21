<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api'
import { useSitio } from '@/stores'
import { linkWhatsapp, parrafos } from '@/utils'
import type { Inicio, SeccionPortada } from '@/types'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'
import HeroPortada from '@/components/HeroPortada.vue'
import ProductRail from '@/components/ProductRail.vue'
import RubroTile from '@/components/RubroTile.vue'
import Franja from '@/components/Franja.vue'
import IconWhatsapp from '@/components/IconWhatsapp.vue'

const sitio = useSitio()
const datos = ref<Inicio | null>(null)
const cargando = ref(true)
const s = computed(() => sitio.sitio!)
const d = computed(() => sitio.diseno!)
const secciones = computed<SeccionPortada[]>(() => d.value.secciones.length ? d.value.secciones : ['beneficios', 'rubros', 'destacados', 'novedades', 'franja', 'nosotros', 'contacto'])
const textoNosotros = computed(() => parrafos(s.value.quienesSomos.texto).slice(0, 2))
const verTodasFamilias = ref(false)
// Tarjetas de la portada: las familias (primer nivel del catálogo). Si son más de 12, las 12 con más productos y "Ver todas"
const familiasPortada = computed(() => {
  const todas = datos.value?.familias ?? []
  if (verTodasFamilias.value || todas.length <= 12) return todas
  const top = new Set([...todas].sort((a, b) => b.cantidad - a.cantidad).slice(0, 12).map((f) => f.id))
  return todas.filter((f) => top.has(f.id))
})
const rubrosFranja = computed(() => [...(datos.value?.rubros ?? [])].sort((a, b) => b.cantidad - a.cantidad).slice(0, 12).map((r) => r.nombre))
const ICONOS_OK = new Set(['truck', 'wallet', 'shield', 'star', 'percent', 'clock', 'phone', 'box', 'check', 'sparkles', 'user'])
const iconoBenef = (i: string) => (ICONOS_OK.has(i) ? i : 'check') as IconName

onMounted(async () => {
  try { datos.value = await api.get<Inicio>('/inicio') } finally { cargando.value = false }
})
</script>

<template>
  <div>
    <HeroPortada :inicio="datos" />

    <!-- Bienvenida / acceso de clientes -->
    <section class="container-page relative z-10 -mt-10">
      <div v-if="!sitio.logueado" v-reveal class="card flex flex-col items-start gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg"><AppIcon name="lock" /></div>
          <div>
            <p class="font-extrabold text-slate-900">¿Ya sos cliente?</p>
            <p class="text-sm text-slate-500">Ingresá para ver tus precios, hacer pedidos y consultar tu cuenta.</p>
          </div>
        </div>
        <div class="flex w-full gap-2 sm:w-auto">
          <RouterLink :to="{ name: 'login' }" class="btn-primary flex-1 sm:flex-none">Ingresar</RouterLink>
          <RouterLink v-if="s.registroHabilitado" :to="{ name: 'registro' }" class="btn-secondary flex-1 sm:flex-none">Quiero ser cliente</RouterLink>
        </div>
      </div>
      <div v-else v-reveal class="card flex flex-wrap items-center justify-between gap-3 p-5">
        <p class="text-slate-700">¡Hola <b>{{ sitio.cliente?.razon }}</b>! Estás viendo tus precios.</p>
        <div class="flex gap-2">
          <RouterLink :to="{ name: 'cuenta' }" class="btn-secondary">Mi cuenta</RouterLink>
          <RouterLink :to="{ name: 'catalogo' }" class="btn-primary">Hacer un pedido</RouterLink>
        </div>
      </div>
    </section>

    <template v-for="sec in secciones" :key="sec">
      <!-- Beneficios -->
      <section v-if="sec === 'beneficios' && d.beneficios.length" class="container-page mt-10">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div v-for="(b, i) in d.beneficios" :key="b.titulo" v-reveal="i" class="beneficio group card flex items-start gap-3 p-4">
            <div class="ic flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-600"><AppIcon :name="iconoBenef(b.icono)" :size="22" /></div>
            <div class="min-w-0"><p class="text-sm font-extrabold leading-tight text-slate-900">{{ b.titulo }}</p><p v-if="b.texto" class="mt-0.5 text-xs text-slate-500">{{ b.texto }}</p></div>
          </div>
        </div>
      </section>

      <!-- Familias con íconos (la sección se sigue llamando "rubros" en la configuración) -->
      <section v-else-if="sec === 'rubros' && sitio.verCatalogo && (cargando || datos?.familias?.length)" class="container-page mt-14">
        <div v-reveal class="mb-5 flex items-end justify-between gap-3">
          <div><p class="text-xs font-bold uppercase tracking-widest text-accent-600">Explorá</p><h2 class="section-title">Nuestras categorías</h2></div>
          <RouterLink :to="{ name: 'catalogo' }" class="group inline-flex items-center gap-1 text-sm font-bold text-brand-600">Ver todo<AppIcon name="arrow-right" :size="16" class="transition group-hover:translate-x-1" /></RouterLink>
        </div>
        <div v-if="cargando" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"><div v-for="i in 6" :key="i" class="skeleton aspect-square rounded-3xl" /></div>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <RubroTile v-for="(f, i) in familiasPortada" :key="f.id" v-reveal="i % 12" :nombre="f.nombre" :familia="f.id" :cantidad="f.cantidad" />
        </div>
        <div v-if="!cargando && datos && datos.familias.length > 12" class="mt-5 text-center">
          <button type="button" class="btn-secondary" @click="verTodasFamilias = !verTodasFamilias">
            {{ verTodasFamilias ? 'Ver menos' : `Ver las ${datos.familias.length} categorías` }}<AppIcon name="chevron-down" :size="16" class="transition" :class="{ 'rotate-180': verTodasFamilias }" />
          </button>
        </div>
      </section>

      <!-- Destacados -->
      <section v-else-if="sec === 'destacados' && sitio.verCatalogo && datos?.destacados.length" class="container-page mt-14">
        <div v-reveal class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-accent-600">Elegidos</p><h2 class="section-title">Destacados</h2></div>
        <ProductRail :productos="datos.destacados" />
      </section>

      <!-- Novedades -->
      <section v-else-if="sec === 'novedades' && sitio.verCatalogo && (cargando || datos?.novedades.length)" class="container-page mt-14">
        <div v-reveal class="mb-4 flex items-end justify-between gap-3">
          <div><p class="text-xs font-bold uppercase tracking-widest text-accent-600">Recién llegados</p><h2 class="section-title">Novedades</h2></div>
          <RouterLink :to="{ name: 'catalogo', query: { nuevos: '1' } }" class="group inline-flex items-center gap-1 text-sm font-bold text-brand-600">Ver todas<AppIcon name="arrow-right" :size="16" class="transition group-hover:translate-x-1" /></RouterLink>
        </div>
        <ProductRail :productos="datos?.novedades ?? []" :cargando="cargando" />
      </section>

      <!-- Franja en movimiento -->
      <div v-else-if="sec === 'franja' && rubrosFranja.length" class="mt-14"><Franja :rubros="rubrosFranja" /></div>

      <!-- Quiénes somos -->
      <section v-else-if="sec === 'nosotros' && textoNosotros.length" class="container-page mt-14">
        <div class="grid items-center gap-8 overflow-hidden rounded-3xl bg-white shadow-card md:grid-cols-2">
          <div v-if="s.quienesSomos.imagen" v-reveal class="relative h-64 overflow-hidden md:h-full md:min-h-[360px]">
            <img :src="s.quienesSomos.imagen" :alt="s.quienesSomos.titulo" loading="lazy" class="zoom-lento h-full w-full object-cover" />
          </div>
          <div v-reveal="1" class="p-6 sm:p-10" :class="{ 'md:col-span-2': !s.quienesSomos.imagen }">
            <p class="text-xs font-bold uppercase tracking-widest text-accent-600">Conocenos</p>
            <h2 class="section-title">{{ s.quienesSomos.titulo }}</h2>
            <p v-for="(p, i) in textoNosotros" :key="i" class="mt-3 whitespace-pre-line leading-relaxed text-slate-600">{{ p }}</p>
            <RouterLink :to="{ name: 'nosotros' }" class="btn-secondary mt-5">Conocé más<AppIcon name="arrow-right" :size="16" /></RouterLink>
          </div>
        </div>
      </section>

      <!-- Contacto -->
      <section v-else-if="sec === 'contacto' && (sitio.whatsapp || s.contacto.telefono)" class="container-page mt-14">
        <div v-reveal class="cta relative overflow-hidden rounded-3xl px-6 py-10 text-center text-white sm:px-12 sm:text-left">
          <div class="relative flex flex-col items-center gap-5 sm:flex-row sm:justify-between">
            <div>
              <p class="text-2xl font-black sm:text-3xl">¿Tenés una consulta?</p>
              <p class="mt-1 text-white/80">Escribinos y te respondemos a la brevedad.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
              <a v-if="sitio.whatsapp" :href="linkWhatsapp(sitio.whatsapp, `Hola ${s.nombre}! Quería hacer una consulta.`)" target="_blank" rel="noopener" class="btn-whatsapp px-6 py-3 text-base"><IconWhatsapp />WhatsApp</a>
              <a v-if="s.contacto.telefono" :href="`tel:${s.contacto.telefono.replace(/[^\d+]/g, '')}`" class="btn bg-white px-6 py-3 text-base text-slate-900 hover:bg-white/90"><AppIcon name="phone" />Llamar</a>
            </div>
          </div>
        </div>
      </section>
    </template>

    <section v-if="!sitio.verCatalogo" class="container-page mt-10">
      <div class="card p-8 text-center">
        <AppIcon name="lock" :size="40" class="mx-auto text-brand-600" />
        <p class="mt-3 text-lg font-bold">El catálogo es exclusivo para clientes</p>
        <p class="mt-1 text-slate-500">Ingresá con tu usuario para ver los productos y tus precios.</p>
        <RouterLink :to="{ name: 'login' }" class="btn-primary mt-4">Ingresar</RouterLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
.beneficio { transition: transform .3s, box-shadow .3s; }
.beneficio:hover { transform: translateY(-3px); box-shadow: var(--shadow-card-hover); }
.beneficio:hover .ic { animation: giro .6s cubic-bezier(.34,1.56,.64,1); }
@keyframes giro { from { transform: rotate(-20deg) scale(.8); } to { transform: none; } }
.zoom-lento { transition: transform 6s ease; }
.zoom-lento:hover { transform: scale(1.08); }
.cta { background: linear-gradient(120deg, var(--c-primario), var(--c-encabezado), var(--c-primario)); background-size: 250% 250%; animation: grad 10s ease infinite; }
.cta::before { content: ''; position: absolute; inset: -50%; background: radial-gradient(circle at 30% 30%, rgb(255 255 255 / .18), transparent 40%); animation: luz 8s ease-in-out infinite alternate; }
@keyframes grad { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
@keyframes luz { to { transform: translate(30%, 20%); } }
:global(.anim-ninguna) .cta, :global(.anim-ninguna) .cta::before { animation: none; }
</style>
