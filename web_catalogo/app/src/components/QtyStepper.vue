<script setup lang="ts">
import AppIcon from './AppIcon.vue'

const cantidad = defineModel<number>({ required: true })
withDefaults(defineProps<{ chico?: boolean; min?: number }>(), { chico: false, min: 0 })

function leer(e: Event): void {
  const n = parseInt((e.target as HTMLInputElement).value, 10)
  cantidad.value = Number.isFinite(n) && n >= 0 ? n : 0
}
</script>

<template>
  <div class="inline-flex items-center overflow-hidden rounded-xl border border-slate-200 bg-white" :class="chico ? 'h-9' : 'h-11'">
    <button type="button" class="flex h-full items-center px-2.5 text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="cantidad <= min"
            aria-label="Restar" @click="cantidad = Math.max(min, cantidad - 1)">
      <AppIcon name="minus" :size="16" />
    </button>
    <input type="number" inputmode="numeric" min="0" :value="cantidad" class="h-full w-12 border-x border-slate-100 text-center text-base font-semibold focus:outline-none sm:text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
           aria-label="Cantidad" @change="leer" @focus="($event.target as HTMLInputElement).select()" />
    <button type="button" class="flex h-full items-center px-2.5 text-slate-600 hover:bg-slate-50" aria-label="Sumar" @click="cantidad = cantidad + 1">
      <AppIcon name="plus" :size="16" />
    </button>
  </div>
</template>
