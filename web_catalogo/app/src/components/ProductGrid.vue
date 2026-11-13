<script setup lang="ts">
import type { Producto } from '@/types'
import ProductCard from './ProductCard.vue'

withDefaults(defineProps<{ productos: Producto[]; cargando?: boolean; esqueletos?: number }>(), { cargando: false, esqueletos: 8 })
</script>

<template>
  <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
    <template v-if="cargando">
      <div v-for="i in esqueletos" :key="i" class="card overflow-hidden">
        <div class="skeleton aspect-square rounded-none" />
        <div class="space-y-2 p-4"><div class="skeleton h-3 w-1/3" /><div class="skeleton h-4 w-full" /><div class="skeleton h-5 w-1/2" /></div>
      </div>
    </template>
    <ProductCard v-for="p in productos" v-else :key="p.codigo" :producto="p" />
  </div>
</template>
