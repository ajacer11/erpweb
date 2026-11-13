<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { Banner } from '@/types'

const props = defineProps<{ banners: Banner[] }>()
const activo = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

const externo = (l: string) => /^https?:\/\//i.test(l)
function siguiente(d = 1): void { activo.value = (activo.value + d + props.banners.length) % props.banners.length }
function reiniciar(): void {
  clearInterval(timer)
  if (props.banners.length > 1) timer = setInterval(() => siguiente(), 6000)
}
let x0: number | null = null
function fin(e: TouchEvent): void {
  if (x0 === null) return
  const dx = (e.changedTouches[0]?.clientX ?? x0) - x0
  if (Math.abs(dx) > 40) { siguiente(dx < 0 ? 1 : -1); reiniciar() }
  x0 = null
}
onMounted(reiniciar)
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <section class="relative overflow-hidden bg-slate-900" @touchstart.passive="x0 = $event.touches[0]?.clientX ?? null" @touchend="fin">
    <div class="relative aspect-[16/9] sm:aspect-[21/8] lg:aspect-[1920/640]">
      <TransitionGroup name="fade">
        <div v-for="(b, i) in banners" v-show="i === activo" :key="b.imagen" class="absolute inset-0">
          <img :src="b.imagen" :alt="b.titulo || ''" class="h-full w-full object-cover" :loading="i === 0 ? 'eager' : 'lazy'" />
          <div v-if="b.titulo || b.subtitulo || b.botonTexto" class="absolute inset-0 flex items-end bg-gradient-to-t from-black/70 via-black/20 to-transparent sm:items-center sm:bg-gradient-to-r">
            <div class="container-page pb-8 text-white sm:pb-0">
              <h2 v-if="b.titulo" class="max-w-xl text-2xl font-extrabold leading-tight drop-shadow sm:text-4xl lg:text-5xl">{{ b.titulo }}</h2>
              <p v-if="b.subtitulo" class="mt-2 max-w-lg text-sm text-white/90 sm:text-lg">{{ b.subtitulo }}</p>
              <template v-if="b.botonTexto && b.botonLink">
                <a v-if="externo(b.botonLink)" :href="b.botonLink" target="_blank" rel="noopener" class="btn-accent mt-4 px-6 py-3">{{ b.botonTexto }}</a>
                <RouterLink v-else :to="b.botonLink" class="btn-accent mt-4 px-6 py-3">{{ b.botonTexto }}</RouterLink>
              </template>
            </div>
          </div>
        </div>
      </TransitionGroup>
    </div>
    <div v-if="banners.length > 1" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2">
      <button v-for="(_, i) in banners" :key="i" type="button" class="h-2 rounded-full transition-all" :class="i === activo ? 'w-6 bg-white' : 'w-2 bg-white/50'"
              :aria-label="`Banner ${i + 1}`" @click="activo = i; reiniciar()" />
    </div>
  </section>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .7s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
