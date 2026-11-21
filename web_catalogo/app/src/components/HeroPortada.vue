<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useSitio } from '@/stores'
import type { Inicio } from '@/types'
import AppIcon from './AppIcon.vue'
import Contador from './Contador.vue'

const props = defineProps<{ inicio: Inicio | null }>()
const sitio = useSitio()
const router = useRouter()
const s = computed(() => sitio.sitio!)
const d = computed(() => sitio.diseno!)

// Banners: rotan con zoom lento (ken burns)
const activo = ref(0)
let timerBanner: ReturnType<typeof setInterval> | undefined
// Palabras que rotan en el título: las configuradas en el ERP o los rubros con más productos
const palabras = computed(() => {
  if (d.value.heroPalabras.length) return d.value.heroPalabras
  return [...(props.inicio?.rubros ?? [])].sort((a, b) => b.cantidad - a.cantidad).slice(0, 6).map((r) => r.nombre.toLowerCase())
})
const iPalabra = ref(0)
let timerPalabra: ReturnType<typeof setInterval> | undefined

const busqueda = ref('')
function buscar(): void {
  const q = busqueda.value.trim()
  router.push({ name: 'catalogo', query: q ? { q } : {} })
}
// Accesos rápidos debajo del buscador: las familias con más productos
const familiasTop = computed(() => [...(props.inicio?.familias ?? [])].sort((a, b) => b.cantidad - a.cantidad).slice(0, 6))
const actual = computed(() => s.value.banners[activo.value])
const externo = (l: string) => /^https?:\/\//i.test(l)

onMounted(() => {
  if (s.value.banners.length > 1) timerBanner = setInterval(() => { activo.value = (activo.value + 1) % s.value.banners.length }, 7000)
  timerPalabra = setInterval(() => { if (palabras.value.length) iPalabra.value = (iPalabra.value + 1) % palabras.value.length }, 2400)
})
onBeforeUnmount(() => { clearInterval(timerBanner); clearInterval(timerPalabra) })
</script>

<template>
  <section class="hero relative isolate overflow-hidden text-white">
    <!-- Fondo: banners con zoom lento, o degradado animado si no hay -->
    <div class="absolute inset-0 -z-10">
      <TransitionGroup name="fade">
        <img v-for="(b, i) in s.banners" v-show="i === activo" :key="b.imagen" :src="b.imagen" alt="" class="kenburns absolute inset-0 h-full w-full object-cover" :loading="i ? 'lazy' : 'eager'" />
      </TransitionGroup>
      <div v-if="!s.banners.length" class="degradado absolute inset-0" />
      <div class="absolute inset-0" style="background: linear-gradient(100deg, color-mix(in srgb, var(--c-encabezado) 92%, transparent) 0%, color-mix(in srgb, var(--c-encabezado) 70%, transparent) 45%, color-mix(in srgb, var(--c-encabezado) 15%, transparent) 100%)" />
      <div class="burbujas" aria-hidden="true"><span v-for="i in 10" :key="i" :style="{ '--i': i }" /></div>
    </div>

    <div class="container-page pb-24 pt-10 sm:pb-32 sm:pt-16 lg:pt-20">
      <p v-if="s.slogan" class="entrada mb-3 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-wider backdrop-blur" style="--d: 0">
        <AppIcon name="sparkles" :size="14" class="text-accent-500" />{{ s.slogan }}
      </p>
      <h1 class="entrada max-w-3xl text-4xl font-black leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl" style="--d: 1">
        {{ actual?.titulo || s.nombre }}
      </h1>
      <p v-if="palabras.length" class="entrada mt-3 text-xl font-semibold text-white/90 sm:text-2xl" style="--d: 2">
        Todo en
        <span class="rotador"><Transition name="palabra" mode="out-in"><span :key="iPalabra" class="text-accent-500">{{ palabras[iPalabra] }}</span></Transition></span>
      </p>
      <p v-else-if="actual?.subtitulo" class="entrada mt-3 max-w-xl text-lg text-white/85" style="--d: 2">{{ actual.subtitulo }}</p>

      <form v-if="d.heroBuscador && sitio.verCatalogo" class="entrada mt-7 flex max-w-xl items-center gap-2 rounded-2xl bg-white p-1.5 shadow-2xl shadow-black/30" style="--d: 3" role="search" @submit.prevent="buscar">
        <AppIcon name="search" class="ml-3 text-slate-400" />
        <input v-model="busqueda" type="search" class="min-w-0 flex-1 bg-transparent py-2.5 text-base text-slate-800 placeholder:text-slate-400 focus:outline-none" placeholder="Buscá por producto, marca o código..." aria-label="Buscar productos" />
        <button type="submit" class="btn-accent px-5">Buscar</button>
      </form>

      <div v-if="familiasTop.length" class="entrada mt-4 flex flex-wrap gap-2" style="--d: 4">
        <RouterLink v-for="f in familiasTop" :key="f.id" :to="{ name: 'catalogo', query: { familia: String(f.id) } }"
                    class="rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur transition hover:bg-white hover:text-slate-900">{{ f.nombre }}</RouterLink>
      </div>

      <div class="entrada mt-6 flex flex-wrap gap-3" style="--d: 5">
        <template v-if="actual?.botonTexto && actual.botonLink">
          <a v-if="externo(actual.botonLink)" :href="actual.botonLink" target="_blank" rel="noopener" class="btn-accent px-6 py-3 text-base">{{ actual.botonTexto }}</a>
          <RouterLink v-else :to="actual.botonLink" class="btn-accent px-6 py-3 text-base">{{ actual.botonTexto }}<AppIcon name="arrow-right" :size="18" /></RouterLink>
        </template>
        <RouterLink v-else :to="{ name: 'catalogo' }" class="btn-accent px-6 py-3 text-base">Ver el catálogo<AppIcon name="arrow-right" :size="18" /></RouterLink>
        <RouterLink v-if="!sitio.logueado" :to="{ name: 'login' }" class="btn border border-white/40 px-6 py-3 text-base text-white hover:bg-white/10">Área de clientes</RouterLink>
      </div>

      <div v-if="d.contadores && inicio?.contadores?.productos" class="entrada mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-white/15 pt-6" style="--d: 6">
        <Contador :valor="inicio.contadores.productos" texto="Productos" />
        <Contador :valor="inicio.contadores.rubros" texto="Rubros" />
        <Contador :valor="inicio.contadores.novedades" texto="Novedades" />
      </div>
    </div>

    <!-- Olas -->
    <div v-if="d.heroOlas" class="olas absolute inset-x-0 bottom-0" aria-hidden="true">
      <svg viewBox="0 0 2880 120" preserveAspectRatio="none" class="ola o1"><path d="M0,60 C360,100 1080,20 1440,60 C1800,100 2520,20 2880,60 L2880,120 L0,120 Z" /></svg>
      <svg viewBox="0 0 2880 120" preserveAspectRatio="none" class="ola o2"><path d="M0,75 C300,40 1140,110 1440,75 C1740,40 2580,110 2880,75 L2880,120 L0,120 Z" /></svg>
      <svg viewBox="0 0 2880 120" preserveAspectRatio="none" class="ola o3"><path d="M0,95 C480,75 960,115 1440,95 C1920,75 2400,115 2880,95 L2880,120 L0,120 Z" /></svg>
    </div>
  </section>
</template>

<style scoped>
.hero { min-height: min(88vh, 760px); display: flex; align-items: center; }
.kenburns { animation: kb 18s ease-out infinite alternate; }
@keyframes kb { from { transform: scale(1); } to { transform: scale(1.14) translate(-1.5%, -1%); } }
.degradado { background: linear-gradient(120deg, var(--c-encabezado), var(--c-primario), var(--c-encabezado)); background-size: 300% 300%; animation: grad 14s ease infinite; }
@keyframes grad { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
.fade-enter-active, .fade-leave-active { transition: opacity 1.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.entrada { animation: entrar .8s cubic-bezier(.22,1,.36,1) both; animation-delay: calc(var(--d) * 110ms + 100ms); }
@keyframes entrar { from { opacity: 0; transform: translateY(22px); filter: blur(4px); } to { opacity: 1; transform: none; filter: none; } }

.rotador { display: inline-block; min-width: 6ch; }
.palabra-enter-active, .palabra-leave-active { transition: all .35s cubic-bezier(.22,1,.36,1); }
.palabra-enter-from { opacity: 0; transform: translateY(14px) rotateX(70deg); }
.palabra-leave-to { opacity: 0; transform: translateY(-14px) rotateX(-70deg); }

.burbujas span {
  position: absolute; bottom: -20px; left: calc(var(--i) * 9.5%); width: calc(6px + var(--i) * 1.4px); aspect-ratio: 1; border-radius: 9999px;
  border: 1.5px solid rgb(255 255 255 / .35); animation: subir calc(9s + var(--i) * .7s) linear infinite; animation-delay: calc(var(--i) * -1.3s);
}
@keyframes subir { from { transform: translateY(0) translateX(0); opacity: 0; } 10% { opacity: 1; } to { transform: translateY(-110vh) translateX(30px); opacity: 0; } }

.olas { height: 90px; }
.ola { position: absolute; bottom: 0; left: 0; width: 200%; height: 100%; }
.ola path { fill: rgb(248 250 252); }
.o1 { opacity: .35; animation: olear 16s linear infinite; }
.o2 { opacity: .6; animation: olear 11s linear infinite reverse; }
.o3 { animation: olear 22s linear infinite; }
@keyframes olear { from { transform: translateX(0); } to { transform: translateX(-50%); } }

:global(.anim-ninguna) .hero *, :global(.anim-ninguna) .hero { animation: none !important; }
:global(.anim-suaves) .kenburns, :global(.anim-suaves) .burbujas span, :global(.anim-suaves) .ola { animation-play-state: paused; }
</style>
