<script setup lang="ts">
import { computed } from 'vue'
import { useSitio } from '@/stores'
import { moneda } from '@/utils'
import type { Producto } from '@/types'

const props = withDefaults(defineProps<{ producto: Producto; grande?: boolean }>(), { grande: false })
const sitio = useSitio()

const conIva = computed(() => sitio.sitio?.catalogo.precioConIva !== false)
const valor = computed(() => (conIva.value ? props.producto.precio : props.producto.precioNeto))
</script>

<template>
  <div v-if="!sitio.verPrecios">
    <RouterLink :to="{ name: 'login' }" class="text-sm font-semibold text-brand-600 hover:underline">Ingresá para ver precios</RouterLink>
  </div>
  <div v-else-if="valor === null" class="text-sm font-semibold text-slate-500">Consultar precio</div>
  <div v-else>
    <span class="font-extrabold tracking-tight text-brand-700" :class="grande ? 'text-3xl' : 'text-lg'">{{ moneda(valor) }}</span>
    <span v-if="!conIva" class="ml-1 text-xs font-medium text-slate-500">+ IVA</span>
    <p v-if="grande && conIva && producto.precioNeto !== null" class="text-xs text-slate-500">Precio sin impuestos nacionales: {{ moneda(producto.precioNeto) }}</p>
  </div>
</template>
