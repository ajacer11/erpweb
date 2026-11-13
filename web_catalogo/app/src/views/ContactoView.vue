<script setup lang="ts">
import { computed } from 'vue'
import { useSitio } from '@/stores'
import { linkWhatsapp } from '@/utils'
import AppIcon from '@/components/AppIcon.vue'
import IconWhatsapp from '@/components/IconWhatsapp.vue'

const sitio = useSitio()
const c = computed(() => sitio.sitio!.contacto)
const telLimpio = computed(() => c.value.telefono.replace(/[^\d+]/g, ''))
</script>

<template>
  <div class="container-page max-w-4xl py-8">
    <h1 class="text-3xl font-extrabold tracking-tight">Contacto</h1>
    <p class="mt-1 text-slate-500">Estamos para ayudarte. Elegí cómo comunicarte.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <a v-if="c.whatsapp" :href="linkWhatsapp(c.whatsapp, `Hola ${sitio.sitio?.nombre}! Quería hacer una consulta.`)" target="_blank" rel="noopener"
         class="card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-card-hover">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#25d366] text-white"><IconWhatsapp :size="26" /></div>
        <div><p class="font-bold">WhatsApp</p><p class="text-sm text-slate-500">Escribinos, respondemos rápido</p></div>
      </a>
      <a v-if="c.telefono" :href="`tel:${telLimpio}`" class="card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-card-hover">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white"><AppIcon name="phone" :size="24" /></div>
        <div><p class="font-bold">Teléfono</p><p class="text-sm text-slate-500">{{ c.telefono }}</p></div>
      </a>
      <a v-if="c.email" :href="`mailto:${c.email}`" class="card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-card-hover">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white"><AppIcon name="mail" :size="24" /></div>
        <div class="min-w-0"><p class="font-bold">Mail</p><p class="truncate text-sm text-slate-500">{{ c.email }}</p></div>
      </a>
      <component :is="c.mapa ? 'a' : 'div'" v-if="c.direccion" :href="c.mapa || undefined" target="_blank" rel="noopener"
                 class="card flex items-center gap-4 p-5" :class="{ 'transition hover:-translate-y-0.5 hover:shadow-card-hover': c.mapa }">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white"><AppIcon name="pin" :size="24" /></div>
        <div><p class="font-bold">Dirección</p><p class="text-sm text-slate-500">{{ c.direccion }}<template v-if="c.localidad">, {{ c.localidad }}</template></p>
          <p v-if="c.mapa" class="text-xs font-semibold text-brand-600">Ver en el mapa</p></div>
      </component>
    </div>

    <div v-if="c.horarios" class="card mt-4 flex gap-4 p-5">
      <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><AppIcon name="clock" :size="24" /></div>
      <div><p class="font-bold">Horarios de atención</p><p class="whitespace-pre-line text-sm text-slate-600">{{ c.horarios }}</p></div>
    </div>

    <div v-if="Object.keys(sitio.sitio!.redes).length" class="mt-6 flex flex-wrap gap-2">
      <a v-for="(url, red) in sitio.sitio!.redes" :key="red" :href="url" target="_blank" rel="noopener" class="chip capitalize"><AppIcon :name="red" :size="16" />{{ red }}</a>
    </div>
  </div>
</template>
