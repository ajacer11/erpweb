<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSitio } from '@/stores'
import { linkWhatsapp } from '@/utils'
import AppHeader from '@/components/AppHeader.vue'
import AppFooter from '@/components/AppFooter.vue'
import BottomNav from '@/components/BottomNav.vue'
import MobileMenu from '@/components/MobileMenu.vue'
import CartDrawer from '@/components/CartDrawer.vue'
import ToastStack from '@/components/ToastStack.vue'
import IconWhatsapp from '@/components/IconWhatsapp.vue'
import VistaRapida from '@/components/VistaRapida.vue'

const sitio = useSitio()
const route = useRoute()
const recargar = () => window.location.reload()
const wpp = computed(() => sitio.sitio?.contacto.whatsappFlotante && sitio.whatsapp ? linkWhatsapp(sitio.whatsapp, `Hola ${sitio.sitio?.nombre}! Quería hacer una consulta.`) : '')
</script>

<template>
  <div v-if="sitio.error" class="flex min-h-screen flex-col items-center justify-center gap-3 p-8 text-center">
    <p class="text-lg font-semibold text-slate-700">{{ sitio.error }}</p>
    <button type="button" class="btn-primary" @click="recargar">Reintentar</button>
  </div>
  <div v-else-if="sitio.sitio" class="flex min-h-screen flex-col">
    <AppHeader />
    <main class="flex-1">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in"><component :is="Component" :key="route.matched[0]?.path" /></Transition>
      </RouterView>
    </main>
    <AppFooter />
    <BottomNav />
    <MobileMenu />
    <CartDrawer />
    <ToastStack />
    <VistaRapida />
    <a v-if="wpp" :href="wpp" target="_blank" rel="noopener" aria-label="Escribinos por WhatsApp"
       class="wpp fixed bottom-20 right-4 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-[#25d366] text-white shadow-lg transition hover:scale-110 lg:bottom-6 lg:right-6 no-print">
      <IconWhatsapp :size="30" />
    </a>
  </div>
</template>

<style>
.page-enter-active, .page-leave-active { transition: opacity .2s ease, transform .2s ease; }
.page-enter-from { opacity: 0; transform: translateY(8px); }
.page-leave-to { opacity: 0; }
.wpp::before { content: ''; position: absolute; inset: 0; border-radius: 9999px; background: #25d366; z-index: -1; animation: onda 2.4s ease-out infinite; }
@keyframes onda { from { transform: scale(1); opacity: .6; } to { transform: scale(1.8); opacity: 0; } }
.anim-ninguna .wpp::before { animation: none; }
</style>
