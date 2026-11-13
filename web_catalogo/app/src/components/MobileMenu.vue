<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useSitio, useUi } from '@/stores'
import type { Filtros } from '@/types'
import AppIcon from './AppIcon.vue'

const sitio = useSitio()
const ui = useUi()
const route = useRoute()
const rubros = ref<Filtros['rubros']>([])

watch(() => route.fullPath, () => { ui.menuAbierto = false })
onMounted(async () => {
  if (!sitio.verCatalogo) return
  try { rubros.value = (await api.get<Filtros>('/filtros')).rubros } catch { /* sin rubros */ }
})
</script>

<template>
  <Transition name="fade">
    <div v-if="ui.menuAbierto" class="fixed inset-0 z-50 bg-slate-900/50 lg:hidden" @click="ui.menuAbierto = false" />
  </Transition>
  <Transition name="slide">
    <aside v-if="ui.menuAbierto" class="fixed inset-y-0 left-0 z-50 flex w-[85%] max-w-sm flex-col bg-white shadow-2xl lg:hidden" aria-label="Menú">
      <div class="flex items-center justify-between px-4 py-3 text-white" style="background: var(--color-head)">
        <span class="truncate text-lg font-bold">{{ sitio.sitio?.nombre }}</span>
        <button type="button" class="rounded-lg p-2 hover:bg-white/10" aria-label="Cerrar menú" @click="ui.menuAbierto = false"><AppIcon name="close" /></button>
      </div>
      <nav class="flex-1 overflow-y-auto py-2">
        <RouterLink to="/" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="home" class="text-brand-600" />Inicio</RouterLink>
        <RouterLink :to="{ name: 'catalogo' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="grid" class="text-brand-600" />Todo el catálogo</RouterLink>
        <RouterLink v-if="sitio.logueado" :to="{ name: 'cuenta' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="user" class="text-brand-600" />Mi cuenta</RouterLink>
        <RouterLink v-else :to="{ name: 'login' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="user" class="text-brand-600" />Ingresar</RouterLink>
        <RouterLink v-if="sitio.sitio?.quienesSomos.texto" :to="{ name: 'nosotros' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="info" class="text-brand-600" />{{ sitio.sitio.quienesSomos.titulo || 'Nosotros' }}</RouterLink>
        <RouterLink :to="{ name: 'contacto' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="phone" class="text-brand-600" />Contacto</RouterLink>
        <RouterLink v-if="sitio.verCatalogo" :to="{ name: 'imprimir' }" class="flex items-center gap-3 px-5 py-3 font-medium hover:bg-slate-50"><AppIcon name="print" class="text-brand-600" />Lista para imprimir</RouterLink>

        <template v-if="rubros.length">
          <p class="mt-3 border-t border-slate-100 px-5 pb-1 pt-4 text-xs font-bold uppercase tracking-wider text-slate-400">Rubros</p>
          <RouterLink v-for="r in rubros" :key="r.nombre" :to="{ name: 'catalogo', query: { rubro: r.nombre } }"
                      class="flex items-center justify-between px-5 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
            <span>{{ r.nombre }}</span><span class="text-xs text-slate-400">{{ r.cantidad }}</span>
          </RouterLink>
        </template>
      </nav>
    </aside>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform .25s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(-100%); }
</style>
