<script setup lang="ts">
import { computed } from 'vue'
import { useSitio } from '@/stores'
import { linkWhatsapp } from '@/utils'
import AppIcon from './AppIcon.vue'
import IconWhatsapp from './IconWhatsapp.vue'

const sitio = useSitio()
const c = computed(() => sitio.sitio?.contacto)
const anio = new Date().getFullYear()
</script>

<template>
  <footer class="mt-16 pb-20 text-white/80 lg:pb-0 no-print" style="background: var(--color-head)">
    <div class="container-page grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
      <div class="lg:col-span-2">
        <img v-if="sitio.sitio?.logo" :src="sitio.sitio.logo" :alt="sitio.sitio.nombre" class="mb-3 h-12 max-w-[220px] object-contain" />
        <p v-else class="mb-2 text-xl font-extrabold text-white">{{ sitio.sitio?.nombre }}</p>
        <p v-if="sitio.sitio?.slogan" class="max-w-md text-sm">{{ sitio.sitio.slogan }}</p>
        <div v-if="sitio.sitio && Object.keys(sitio.sitio.redes).length" class="mt-4 flex gap-2">
          <a v-for="(url, red) in sitio.sitio.redes" :key="red" :href="url" target="_blank" rel="noopener" :aria-label="red"
             class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">
            <AppIcon :name="red" :size="18" />
          </a>
        </div>
      </div>
      <div>
        <p class="mb-3 font-bold text-white">Contacto</p>
        <ul class="space-y-2 text-sm">
          <li v-if="c?.whatsapp"><a :href="linkWhatsapp(c.whatsapp)" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-white"><IconWhatsapp :size="16" />WhatsApp</a></li>
          <li v-if="c?.telefono"><a :href="`tel:${c.telefono.replace(/[^\d+]/g, '')}`" class="flex items-center gap-2 hover:text-white"><AppIcon name="phone" :size="16" />{{ c.telefono }}</a></li>
          <li v-if="c?.email"><a :href="`mailto:${c.email}`" class="flex items-center gap-2 break-all hover:text-white"><AppIcon name="mail" :size="16" />{{ c.email }}</a></li>
          <li v-if="c?.direccion" class="flex items-start gap-2"><AppIcon name="pin" :size="16" class="mt-0.5" /><span>{{ c.direccion }}<template v-if="c.localidad">, {{ c.localidad }}</template></span></li>
        </ul>
      </div>
      <div>
        <p class="mb-3 font-bold text-white">Navegá</p>
        <ul class="space-y-2 text-sm">
          <li><RouterLink :to="{ name: 'catalogo' }" class="hover:text-white">Catálogo</RouterLink></li>
          <li v-if="sitio.sitio?.quienesSomos.texto"><RouterLink :to="{ name: 'nosotros' }" class="hover:text-white">{{ sitio.sitio.quienesSomos.titulo || 'Nosotros' }}</RouterLink></li>
          <li><RouterLink :to="{ name: 'contacto' }" class="hover:text-white">Contacto</RouterLink></li>
          <li><RouterLink :to="sitio.logueado ? { name: 'cuenta' } : { name: 'login' }" class="hover:text-white">Área de clientes</RouterLink></li>
          <li v-if="!sitio.logueado && sitio.sitio?.registroHabilitado"><RouterLink :to="{ name: 'registro' }" class="hover:text-white">Quiero ser cliente</RouterLink></li>
        </ul>
        <p v-if="c?.horarios" class="mt-4 whitespace-pre-line text-xs text-white/60">{{ c.horarios }}</p>
      </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/50">© {{ anio }} {{ sitio.sitio?.nombre }}</div>
  </footer>
</template>
