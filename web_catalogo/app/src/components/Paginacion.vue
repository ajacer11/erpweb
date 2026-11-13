<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps<{ pagina: number; paginas: number }>()
const emit = defineEmits<{ ir: [pagina: number] }>()

const numeros = computed(() => {
  const { pagina, paginas } = props
  const set = new Set([1, paginas, pagina - 1, pagina, pagina + 1].filter((n) => n >= 1 && n <= paginas))
  const lista = [...set].sort((a, b) => a - b)
  const out: (number | '…')[] = []
  lista.forEach((n, i) => { if (i && n - (lista[i - 1] ?? n) > 1) out.push('…'); out.push(n) })
  return out
})
</script>

<template>
  <nav v-if="paginas > 1" class="flex items-center justify-center gap-1" aria-label="Paginación">
    <button type="button" class="btn-ghost px-3" :disabled="pagina <= 1" aria-label="Anterior" @click="emit('ir', pagina - 1)"><AppIcon name="chevron-left" /></button>
    <template v-for="(n, i) in numeros" :key="i">
      <span v-if="n === '…'" class="px-2 text-slate-400">…</span>
      <button v-else type="button" class="h-10 min-w-10 rounded-xl px-3 text-sm font-semibold transition"
              :class="n === pagina ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="emit('ir', n)">{{ n }}</button>
    </template>
    <button type="button" class="btn-ghost px-3" :disabled="pagina >= paginas" aria-label="Siguiente" @click="emit('ir', pagina + 1)"><AppIcon name="chevron-right" /></button>
  </nav>
</template>
