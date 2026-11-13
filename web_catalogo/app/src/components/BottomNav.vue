<script setup lang="ts">
import { ref, watch } from 'vue'
import { useCarrito, useSitio, useUi } from '@/stores'
import AppIcon from './AppIcon.vue'

const sitio = useSitio()
const carrito = useCarrito()
const ui = useUi()
const salto = ref(false)
watch(() => ui.pulsoCarrito, () => { salto.value = false; requestAnimationFrame(() => { salto.value = true }) })
</script>

<template>
  <!-- Barra inferior de navegación: solo celular -->
  <nav class="glass fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 pb-safe lg:hidden no-print" aria-label="Navegación principal">
    <div class="grid grid-cols-4 text-[11px] font-medium text-slate-500">
      <RouterLink to="/" class="flex flex-col items-center gap-0.5 py-2" exact-active-class="text-brand-600">
        <AppIcon name="home" :size="22" /><span>Inicio</span>
      </RouterLink>
      <RouterLink :to="{ name: 'catalogo' }" class="flex flex-col items-center gap-0.5 py-2" active-class="text-brand-600">
        <AppIcon name="grid" :size="22" /><span>Catálogo</span>
      </RouterLink>
      <button type="button" data-carrito class="relative flex flex-col items-center gap-0.5 py-2" :class="{ salto }" @click="ui.carritoAbierto = true" @animationend="salto = false">
        <AppIcon name="cart" :size="22" /><span>Mi pedido</span>
        <span v-if="carrito.cantidadItems" class="absolute left-1/2 top-1 ml-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{{ carrito.cantidadItems }}</span>
      </button>
      <RouterLink :to="sitio.logueado ? { name: 'cuenta' } : { name: 'login' }" class="flex flex-col items-center gap-0.5 py-2" active-class="text-brand-600">
        <AppIcon name="user" :size="22" /><span>{{ sitio.logueado ? 'Mi cuenta' : 'Ingresar' }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<style scoped>
.salto { animation: salto .6s cubic-bezier(.34,1.56,.64,1); }
@keyframes salto { 0% { transform: scale(1); } 30% { transform: scale(1.3) translateY(-4px); } 100% { transform: scale(1); } }
</style>
