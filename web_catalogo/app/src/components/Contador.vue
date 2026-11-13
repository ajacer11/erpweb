<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{ valor: number; texto: string }>()
const mostrado = ref(0)
const el = ref<HTMLElement | null>(null)
let io: IntersectionObserver | null = null

function animar(): void {
  if (document.documentElement.classList.contains('anim-ninguna')) { mostrado.value = props.valor; return }
  const t0 = performance.now()
  const dur = 1400
  const paso = (t: number) => {
    const p = Math.min(1, (t - t0) / dur)
    mostrado.value = Math.round(props.valor * (1 - Math.pow(1 - p, 3)))
    if (p < 1) requestAnimationFrame(paso)
  }
  requestAnimationFrame(paso)
}
onMounted(() => {
  io = new IntersectionObserver(([e]) => { if (e?.isIntersecting) { animar(); io?.disconnect() } }, { threshold: 0.4 })
  if (el.value) io.observe(el.value)
})
onBeforeUnmount(() => io?.disconnect())
</script>

<template>
  <div ref="el" class="text-center">
    <p class="text-3xl font-black tracking-tight text-white sm:text-4xl">{{ mostrado.toLocaleString('es-AR') }}<span class="text-accent-500">+</span></p>
    <p class="mt-0.5 text-xs font-semibold uppercase tracking-wider text-white/70">{{ texto }}</p>
  </div>
</template>
