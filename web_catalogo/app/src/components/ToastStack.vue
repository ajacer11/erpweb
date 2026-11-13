<script setup lang="ts">
import { useUi } from '@/stores'
import AppIcon from './AppIcon.vue'

const ui = useUi()
const estilos = { ok: 'border-emerald-200 bg-emerald-50 text-emerald-800', info: 'border-brand-200 bg-brand-50 text-brand-800', error: 'border-red-200 bg-red-50 text-red-800' }
const iconos = { ok: 'check', info: 'info', error: 'alert' } as const
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6 lg:bottom-4 no-print">
    <TransitionGroup name="toast">
      <div v-for="t in ui.toasts" :key="t.id" role="status"
           class="pointer-events-auto flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium shadow-card-hover" :class="estilos[t.tipo]">
        <AppIcon :name="iconos[t.tipo]" :size="18" />
        <span class="whitespace-pre-line">{{ t.mensaje }}</span>
        <button type="button" class="ml-2 opacity-60 hover:opacity-100" aria-label="Cerrar" @click="ui.cerrarToast(t.id)"><AppIcon name="close" :size="14" /></button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all .25s ease; }
.toast-enter-from { opacity: 0; transform: translateY(12px); }
.toast-leave-to { opacity: 0; transform: translateY(6px) scale(.98); }
</style>
