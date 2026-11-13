<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
</script>

<template>
  <div class="container-page flex min-h-[60vh] flex-col items-center justify-center py-16 text-center">
    <AppIcon name="search" :size="48" class="text-slate-300" />
    <h1 class="mt-4 text-2xl font-extrabold">No encontramos esta página</h1>
    <p class="mt-1 text-slate-500">Puede que el link esté mal o que la página ya no exista.</p>
    <div class="mt-6 flex gap-2">
      <RouterLink to="/" class="btn-primary">Ir al inicio</RouterLink>
      <RouterLink :to="{ name: 'catalogo' }" class="btn-secondary">Ver el catálogo</RouterLink>
    </div>
  </div>
</template>
