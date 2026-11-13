<script setup lang="ts">
import { ref } from 'vue'
import type { Producto } from '@/types'
import AppIcon from './AppIcon.vue'
import ProductCard from './ProductCard.vue'

defineProps<{ productos: Producto[]; cargando?: boolean }>()
const pista = ref<HTMLElement | null>(null)
const mover = (d: number) => pista.value?.scrollBy({ left: d * pista.value.clientWidth * 0.85, behavior: 'smooth' })
</script>

<template>
  <div class="group/rail relative">
    <div ref="pista" class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth px-4 pb-4 pt-1 scrollbar-hide sm:gap-4">
      <template v-if="cargando">
        <div v-for="i in 5" :key="i" class="w-[46%] shrink-0 sm:w-[30%] lg:w-[23%]"><div class="skeleton aspect-[3/4]" /></div>
      </template>
      <div v-for="(p, i) in productos" v-else :key="p.codigo" v-reveal="i" class="w-[46%] shrink-0 snap-start sm:w-[30%] lg:w-[23%]">
        <ProductCard :producto="p" />
      </div>
    </div>
    <button type="button" class="rail-btn left-0 -translate-x-1/2" aria-label="Anteriores" @click="mover(-1)"><AppIcon name="chevron-left" /></button>
    <button type="button" class="rail-btn right-0 translate-x-1/2" aria-label="Siguientes" @click="mover(1)"><AppIcon name="chevron-right" /></button>
  </div>
</template>

<style scoped>
.rail-btn {
  position: absolute; top: 40%; display: none; height: 2.75rem; width: 2.75rem; align-items: center; justify-content: center;
  border-radius: 9999px; background: white; color: rgb(30 41 59); box-shadow: 0 8px 24px rgb(15 23 42 / .18);
  opacity: 0; transition: opacity .2s, transform .2s;
}
@media (min-width: 1024px) { .rail-btn { display: flex; } }
.group\/rail:hover .rail-btn { opacity: 1; }
.rail-btn:hover { background: var(--c-primario); color: white; }
</style>
