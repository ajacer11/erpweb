<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useSitio } from '@/stores'
import { parrafos } from '@/utils'

const sitio = useSitio()
const q = computed(() => sitio.sitio!.quienesSomos)
onMounted(() => { document.title = `${q.value.titulo} · ${sitio.sitio?.nombre}` })
</script>

<template>
  <div>
    <section class="text-white" style="background: linear-gradient(135deg, var(--color-head), var(--color-brand-700))">
      <div class="container-page py-12 sm:py-16">
        <h1 class="text-3xl font-extrabold sm:text-4xl">{{ q.titulo || 'Quiénes somos' }}</h1>
        <p v-if="sitio.sitio?.slogan" class="mt-2 text-lg text-white/80">{{ sitio.sitio.slogan }}</p>
      </div>
    </section>
    <div class="container-page py-10">
      <div class="grid gap-8" :class="{ 'lg:grid-cols-[1fr_420px]': q.imagen }">
        <div class="card p-6 sm:p-10">
          <p v-for="(p, i) in parrafos(q.texto)" :key="i" class="mb-4 whitespace-pre-line text-lg leading-relaxed text-slate-700 last:mb-0">{{ p }}</p>
          <p v-if="!q.texto" class="text-slate-500">Próximamente.</p>
        </div>
        <img v-if="q.imagen" :src="q.imagen" :alt="q.titulo" class="w-full rounded-2xl object-cover shadow-card" />
      </div>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <RouterLink :to="{ name: 'catalogo' }" class="btn-primary px-6 py-3">Ver el catálogo</RouterLink>
        <RouterLink :to="{ name: 'contacto' }" class="btn-secondary px-6 py-3">Contactanos</RouterLink>
      </div>
    </div>
  </div>
</template>
