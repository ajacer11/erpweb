<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCarrito, useSitio, useUi } from '@/stores'
import AppIcon from './AppIcon.vue'

const sitio = useSitio()
const carrito = useCarrito()
const ui = useUi()
const route = useRoute()
const router = useRouter()

const oscuro = computed(() => sitio.diseno?.encabezado === 'oscuro')
const busqueda = ref(String(route.query.q ?? ''))
watch(() => route.query.q, (q) => { busqueda.value = String(q ?? '') })

// Se achica al bajar
const compacto = ref(false)
// Con histéresis: al achicarse la página pierde altura y el scroll salta; sin margen oscilaría
const onScroll = () => {
  const y = window.scrollY
  if (!compacto.value && y > 120) compacto.value = true
  else if (compacto.value && y < 20) compacto.value = false
}
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

// El ícono del carrito "salta" al agregar
const salto = ref(false)
watch(() => ui.pulsoCarrito, () => { salto.value = false; requestAnimationFrame(() => { salto.value = true }) })

function buscar(): void {
  const q = busqueda.value.trim()
  router.push({ name: 'catalogo', query: q ? { q } : {} })
  ;(document.activeElement as HTMLElement | null)?.blur()
}
</script>

<template>
  <!-- El anuncio queda fuera del bloque fijo: se va con el scroll y vuelve al subir, sin cambiar alturas -->
  <div v-if="sitio.diseno?.anuncio" class="relative z-40 text-center text-xs font-semibold text-white no-print" style="background: var(--color-head)">
    <p class="container-page py-1.5">{{ sitio.diseno.anuncio }}</p>
  </div>
  <header class="sticky top-0 z-40 no-print">
    <div class="transition-all duration-300" :class="[oscuro ? 'text-white' : 'glass text-slate-800', compacto ? 'shadow-lg' : 'shadow-sm']"
         :style="oscuro ? 'background: var(--color-head)' : ''">
      <div class="container-page flex items-center gap-2 transition-all duration-300 sm:gap-4" :class="compacto ? 'py-1.5' : 'py-2.5 sm:py-3'">
        <button type="button" class="-ml-2 rounded-lg p-2 lg:hidden" :class="oscuro ? 'hover:bg-white/10' : 'hover:bg-slate-100'" aria-label="Abrir menú" @click="ui.menuAbierto = true">
          <AppIcon name="menu" :size="24" />
        </button>

        <RouterLink to="/" class="flex min-w-0 shrink-0 items-center gap-2" :aria-label="`${sitio.sitio?.nombre} - Inicio`">
          <img v-if="sitio.sitio?.logo" :src="sitio.sitio.logo" :alt="sitio.sitio.nombre" class="logo object-contain transition-all duration-300"
               :class="[compacto ? 'h-8 sm:h-9' : 'h-10 sm:h-12', oscuro ? 'rounded-lg bg-white px-2 py-1' : '']" />
          <span v-else class="truncate text-lg font-extrabold tracking-tight sm:text-xl">{{ sitio.sitio?.nombre }}</span>
        </RouterLink>

        <form v-if="sitio.verCatalogo" class="relative hidden flex-1 md:block lg:mx-4" role="search" @submit.prevent="buscar">
          <input v-model="busqueda" type="search" class="w-full rounded-xl border-0 py-2.5 pl-4 pr-11 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                 :class="oscuro ? 'bg-white/95' : 'bg-slate-100'" placeholder="¿Qué estás buscando?" aria-label="Buscar productos" />
          <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-500 hover:text-brand-600" aria-label="Buscar"><AppIcon name="search" :size="18" /></button>
        </form>

        <nav class="ml-auto hidden items-center gap-1 lg:flex">
          <RouterLink :to="{ name: 'catalogo' }" class="nav-link">Catálogo</RouterLink>
          <RouterLink v-if="sitio.sitio?.quienesSomos.texto" :to="{ name: 'nosotros' }" class="nav-link">{{ sitio.sitio.quienesSomos.titulo || 'Nosotros' }}</RouterLink>
          <RouterLink :to="{ name: 'contacto' }" class="nav-link">Contacto</RouterLink>
        </nav>

        <div class="ml-auto flex items-center gap-1 lg:ml-2">
          <RouterLink :to="sitio.logueado ? { name: 'cuenta' } : { name: 'login' }" class="flex items-center gap-2 rounded-xl px-2 py-2 sm:px-3" :class="oscuro ? 'hover:bg-white/10' : 'hover:bg-slate-100'">
            <AppIcon name="user" :size="22" />
            <span class="hidden max-w-[140px] truncate text-sm font-semibold sm:block">{{ sitio.logueado ? sitio.cliente?.razon : 'Ingresar' }}</span>
          </RouterLink>
          <button type="button" data-carrito class="relative flex items-center gap-2 rounded-xl px-2 py-2 sm:px-3" :class="[oscuro ? 'hover:bg-white/10' : 'hover:bg-slate-100', { salto }]"
                  aria-label="Ver mi pedido" @click="ui.carritoAbierto = true" @animationend="salto = false">
            <AppIcon name="cart" :size="22" />
            <span class="hidden text-sm font-semibold sm:block">Mi pedido</span>
            <span v-if="carrito.cantidadItems" :key="carrito.cantidadItems"
                  class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 animate-pop items-center justify-center rounded-full bg-accent-500 px-1 text-[11px] font-bold text-white sm:left-6 sm:right-auto">
              {{ carrito.cantidadItems }}
            </span>
          </button>
        </div>
      </div>

      <form v-if="sitio.verCatalogo && route.name !== 'inicio'" class="container-page relative pb-2.5 md:hidden" role="search" @submit.prevent="buscar">
        <input v-model="busqueda" type="search" class="w-full rounded-xl border-0 py-2.5 pl-4 pr-11 text-base text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
               :class="oscuro ? 'bg-white/95' : 'bg-slate-100'" placeholder="¿Qué estás buscando?" aria-label="Buscar productos" />
        <button type="submit" class="absolute right-5 top-[calc(50%-5px)] -translate-y-1/2 p-2 text-slate-500" aria-label="Buscar"><AppIcon name="search" :size="18" /></button>
      </form>
    </div>
  </header>
</template>

<style scoped>
.nav-link { position: relative; border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem; font-weight: 600; }
.nav-link::after { content: ''; position: absolute; left: .75rem; right: .75rem; bottom: .3rem; height: 2px; border-radius: 2px; background: var(--c-secundario); transform: scaleX(0); transition: transform .25s ease; }
.nav-link:hover::after, .nav-link.router-link-active::after { transform: scaleX(1); }
.salto { animation: salto .6s cubic-bezier(.34,1.56,.64,1); }
@keyframes salto { 0% { transform: scale(1); } 30% { transform: scale(1.35) rotate(-10deg); } 60% { transform: scale(.9) rotate(6deg); } 100% { transform: scale(1); } }
</style>
