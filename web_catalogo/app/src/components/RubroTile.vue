<script setup lang="ts">
import { computed, ref } from 'vue'
import { useSitio } from '@/stores'
import { matiz } from '@/utils'
import RubroIcon from './RubroIcon.vue'

/** Tarjeta con ícono animado. Con `familia` lleva al catálogo de esa familia; si no, al del rubro `nombre`. */
const props = withDefaults(defineProps<{ nombre: string; familia?: number; cantidad?: number; chico?: boolean; activo?: boolean }>(), { familia: 0, cantidad: 0, chico: false, activo: false })
const sitio = useSitio()
const destino = computed(() => ({ name: 'catalogo', query: props.familia ? { familia: String(props.familia) } : { rubro: props.nombre } }))
const h = computed(() => matiz(props.nombre))
const tipo = computed(() => sitio.diseno?.rubrosIconos?.[props.nombre] ?? '')

// Inclinación 3D siguiendo el mouse
const el = ref<{ $el: HTMLElement } | null>(null)
const tilt = ref('')
function mover(e: PointerEvent): void {
  if (e.pointerType !== 'mouse' || !el.value?.$el || document.documentElement.classList.contains('anim-ninguna')) return
  const r = el.value.$el.getBoundingClientRect()
  const x = (e.clientX - r.left) / r.width - 0.5
  const y = (e.clientY - r.top) / r.height - 0.5
  tilt.value = `perspective(600px) rotateY(${x * 14}deg) rotateX(${-y * 14}deg) translateY(-4px)`
}
</script>

<template>
  <RouterLink ref="el" :to="destino" class="rubro-tile group" :class="{ chico, activo }"
              :style="{ '--h': h, transform: tilt }" @pointermove="mover" @pointerleave="tilt = ''">
    <span class="halo" aria-hidden="true" />
    <span class="icono"><RubroIcon :nombre="nombre" :tipo="tipo" :size="chico ? 30 : 52" /></span>
    <span class="nombre">{{ nombre }}</span>
    <span v-if="!chico && cantidad" class="cant">{{ cantidad }} productos</span>
  </RouterLink>
</template>

<style scoped>
.rubro-tile {
  --c1: hsl(var(--h) 75% 52%);
  --c2: hsl(calc(var(--h) + 40) 80% 45%);
  position: relative; isolation: isolate; overflow: hidden;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
  padding: 1.1rem .75rem 1rem; border-radius: 1.25rem; text-align: center;
  background: white; border: 1px solid rgb(226 232 240);
  box-shadow: 0 1px 2px rgb(15 23 42 / .04), 0 6px 18px rgb(15 23 42 / .06);
  transition: transform .25s ease, box-shadow .25s ease, border-color .25s;
  will-change: transform;
}
.rubro-tile:hover { box-shadow: 0 18px 40px -12px hsl(var(--h) 70% 40% / .45); border-color: hsl(var(--h) 70% 75%); }
.halo {
  position: absolute; inset: -40% -40% auto auto; width: 140%; aspect-ratio: 1; z-index: -1; border-radius: 9999px;
  background: radial-gradient(circle at center, hsl(var(--h) 85% 60% / .18), transparent 60%);
  transition: transform .5s ease; transform: translate(20%, -10%);
}
.rubro-tile:hover .halo { transform: translate(0, 0) scale(1.15); }
.icono {
  display: grid; place-items: center; width: 84px; height: 84px; border-radius: 1.4rem; color: white;
  background: linear-gradient(135deg, var(--c1), var(--c2));
  box-shadow: 0 10px 22px -8px hsl(var(--h) 75% 45% / .7), inset 0 1px 0 rgb(255 255 255 / .35);
  transition: transform .35s cubic-bezier(.34,1.56,.64,1);
}
.rubro-tile:hover .icono { transform: scale(1.08) rotate(-4deg); }
.nombre { font-weight: 800; font-size: .85rem; line-height: 1.15; color: rgb(15 23 42); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cant { font-size: .7rem; color: rgb(100 116 139); }

.chico { flex-direction: row; padding: .35rem .85rem .35rem .4rem; border-radius: 9999px; gap: .5rem; white-space: nowrap; }
.chico .icono { width: 38px; height: 38px; border-radius: 9999px; }
.chico .nombre { font-size: .8rem; -webkit-line-clamp: 1; }
.chico .halo { display: none; }
.chico.activo { border-color: hsl(var(--h) 70% 50%); background: hsl(var(--h) 80% 96%); }
</style>
