<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps<{ fotos: string[]; alt: string }>()
const activa = ref(0)
const zoom = ref(false)
watch(() => props.fotos, () => { activa.value = 0 })

// Deslizar con el dedo en celular
let x0: number | null = null
function inicio(e: TouchEvent): void { x0 = e.touches[0]?.clientX ?? null }
function fin(e: TouchEvent): void {
  if (x0 === null) return
  const dx = (e.changedTouches[0]?.clientX ?? x0) - x0
  if (Math.abs(dx) > 40) mover(dx < 0 ? 1 : -1)
  x0 = null
}
function mover(d: number): void {
  const n = props.fotos.length
  if (n) activa.value = (activa.value + d + n) % n
}
</script>

<template>
  <div class="flex flex-col gap-3 md:flex-row-reverse">
    <div class="relative aspect-square flex-1 overflow-hidden rounded-2xl border border-slate-100 bg-white" @touchstart.passive="inicio" @touchend="fin">
      <template v-if="fotos.length">
        <img :src="fotos[activa]" :alt="alt" class="h-full w-full cursor-zoom-in object-contain p-3" @click="zoom = true" />
        <template v-if="fotos.length > 1">
          <button type="button" class="absolute left-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/90 p-2 shadow md:block" aria-label="Anterior" @click="mover(-1)"><AppIcon name="chevron-left" /></button>
          <button type="button" class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/90 p-2 shadow md:block" aria-label="Siguiente" @click="mover(1)"><AppIcon name="chevron-right" /></button>
          <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5 md:hidden">
            <span v-for="(_, i) in fotos" :key="i" class="h-1.5 rounded-full transition-all" :class="i === activa ? 'w-5 bg-brand-600' : 'w-1.5 bg-slate-300'" />
          </div>
        </template>
      </template>
      <div v-else class="flex h-full items-center justify-center text-slate-300"><AppIcon name="box" :size="80" :stroke-width="1.2" /></div>
    </div>
    <div v-if="fotos.length > 1" class="hidden gap-2 md:flex md:w-20 md:flex-col">
      <button v-for="(f, i) in fotos" :key="f" type="button" class="h-20 w-20 overflow-hidden rounded-xl border-2 bg-white"
              :class="i === activa ? 'border-brand-600' : 'border-slate-100 hover:border-slate-300'" :aria-label="`Ver foto ${i + 1}`" @click="activa = i">
        <img :src="f" alt="" class="h-full w-full object-contain" />
      </button>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="zoom" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/90 p-4" @click="zoom = false" @touchstart.passive="inicio" @touchend="fin">
      <img :src="fotos[activa]" :alt="alt" class="max-h-full max-w-full object-contain" @click.stop />
      <button type="button" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white" aria-label="Cerrar" @click="zoom = false"><AppIcon name="close" :size="24" /></button>
      <template v-if="fotos.length > 1">
        <button type="button" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white" aria-label="Anterior" @click.stop="mover(-1)"><AppIcon name="chevron-left" :size="24" /></button>
        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white" aria-label="Siguiente" @click.stop="mover(1)"><AppIcon name="chevron-right" :size="24" /></button>
      </template>
    </div>
  </Teleport>
</template>
