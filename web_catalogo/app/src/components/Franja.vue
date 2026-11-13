<script setup lang="ts">
import RubroIcon from './RubroIcon.vue'
import { useSitio } from '@/stores'

defineProps<{ rubros: string[] }>()
const sitio = useSitio()
</script>

<template>
  <!-- Franja en movimiento con los rubros (se repite dos veces para que el loop no tenga corte) -->
  <section class="franja relative overflow-hidden py-5 text-white" style="background: linear-gradient(90deg, var(--c-primario), var(--c-encabezado))">
    <div class="pista flex w-max">
      <template v-for="vuelta in 2" :key="vuelta">
        <RouterLink v-for="r in rubros" :key="`${vuelta}-${r}`" :to="{ name: 'catalogo', query: { rubro: r } }"
                    class="group mr-10 flex items-center gap-3 whitespace-nowrap text-lg font-black uppercase tracking-wide opacity-90 hover:opacity-100" :aria-hidden="vuelta === 2">
          <RubroIcon :nombre="r" :tipo="sitio.diseno?.rubrosIconos?.[r] ?? ''" :size="34" class="text-accent-500" />{{ r }}
          <span class="ml-6 text-accent-500">✦</span>
        </RouterLink>
      </template>
    </div>
  </section>
</template>

<style scoped>
.pista { animation: correr 50s linear infinite; }
.franja:hover .pista { animation-play-state: paused; }
@keyframes correr { from { transform: translateX(0); } to { transform: translateX(-50%); } }
:global(.anim-ninguna) .pista { animation: none; }
</style>
