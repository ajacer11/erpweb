<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCarrito, useSitio } from '@/stores'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'

const sitio = useSitio()
const carrito = useCarrito()
const router = useRouter()

const secciones = computed(() => [
  { to: 'cuenta', texto: 'Resumen', icono: 'home' as IconName },
  { to: 'pedidos', texto: 'Mis pedidos', icono: 'box' as IconName },
  { to: 'movimientos', texto: 'Cuenta corriente', icono: 'list' as IconName },
  { to: 'comprobantes', texto: 'Facturas', icono: 'file' as IconName },
  ...(sitio.sitio?.avisosPagoHabilitados ? [{ to: 'pagos', texto: 'Informar pago', icono: 'wallet' as IconName }] : []),
  { to: 'datos', texto: 'Mis datos', icono: 'user' as IconName },
])

async function salir(): Promise<void> {
  await sitio.logout()
  carrito.actualizarPrecios().catch(() => undefined)
  router.push('/')
}
</script>

<template>
  <div class="container-page py-6">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm text-slate-500">Área de clientes</p>
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ sitio.cliente?.razon }}</h1>
      </div>
      <button type="button" class="btn-ghost text-slate-500" @click="salir"><AppIcon name="logout" :size="18" />Salir</button>
    </div>

    <div class="grid gap-6 lg:grid-cols-[220px_1fr]">
      <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-hide lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0" aria-label="Secciones de la cuenta">
        <RouterLink v-for="s in secciones" :key="s.to" :to="{ name: s.to }"
                    class="flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-brand-300 lg:border-0 lg:bg-transparent lg:px-3 lg:py-2.5 lg:hover:bg-white"
                    exact-active-class="!border-brand-600 !bg-brand-600 !text-white lg:!bg-brand-600"
                    :class="{ '!border-brand-600 !bg-brand-600 !text-white': s.to === 'pedidos' && $route.name === 'pedido-detalle' }">
          <AppIcon :name="s.icono" :size="18" />{{ s.texto }}
        </RouterLink>
      </nav>
      <div class="min-w-0"><RouterView /></div>
    </div>
  </div>
</template>
