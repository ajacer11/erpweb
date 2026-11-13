import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { api } from './api'
import type { Cliente, ItemCarrito, Producto, Sitio } from './types'

// ============================================================ Sitio + sesión del cliente
export const useSitio = defineStore('sitio', () => {
  const sitio = ref<Sitio | null>(null)
  const cliente = ref<Cliente | null>(null)
  const error = ref<string | null>(null)

  const logueado = computed(() => cliente.value !== null)
  /** ¿Se muestran precios? (logueado siempre; si no, según configuración) */
  const verPrecios = computed(() => logueado.value || !!sitio.value?.catalogo.preciosPublicos)
  const verCatalogo = computed(() => logueado.value || !!sitio.value?.catalogo.publico)
  /** Enviar el pedido: requiere ingresar. */
  const puedePedir = computed(() => logueado.value && !!sitio.value?.pedidos.habilitados)
  /** Agregar al pedido: logueado, o cualquiera si la empresa permite armarlo sin ingresar. */
  const puedeAgregar = computed(() => !!sitio.value?.pedidos.habilitados && (logueado.value || !!sitio.value?.diseno.carritoSinLogin))
  const diseno = computed(() => sitio.value?.diseno)
  const whatsapp = computed(() => sitio.value?.contacto.whatsapp || '')

  async function cargar(): Promise<void> {
    try {
      const s = await api.get<Sitio>('/sitio')
      sitio.value = s
      cliente.value = s.cliente
      aplicarIdentidad(s)
    } catch (e) {
      error.value = (e as Error).message
    }
  }

  async function login(usuario: string, clave: string): Promise<void> {
    const r = await api.post<{ cliente: Cliente }>('/auth/login', { usuario, clave })
    cliente.value = r.cliente
  }

  async function activar(token: string, clave: string): Promise<void> {
    const r = await api.post<{ cliente: Cliente }>('/auth/activar', { token, clave })
    cliente.value = r.cliente
  }

  async function logout(): Promise<void> {
    await api.post('/auth/logout').catch(() => undefined)
    cliente.value = null
  }

  function sesionVencida(): void { cliente.value = null }

  return { sitio, cliente, error, logueado, verPrecios, verCatalogo, puedePedir, puedeAgregar, diseno, whatsapp, cargar, login, activar, logout, sesionVencida }
})

function aplicarIdentidad(s: Sitio): void {
  const root = document.documentElement.style
  root.setProperty('--c-primario', s.colores.primario)
  root.setProperty('--c-secundario', s.colores.secundario)
  root.setProperty('--c-encabezado', s.colores.encabezado)
  document.documentElement.classList.add(`anim-${s.diseno.animaciones}`)
  if (s.diseno.animaciones === 'ninguna' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('anim-ninguna')
  document.querySelector('meta[name=theme-color]')?.setAttribute('content', s.colores.encabezado)
  if (s.descripcion) document.querySelector('meta[name=description]')?.setAttribute('content', s.descripcion)
  if (s.favicon) {
    for (const rel of ['icon', 'apple-touch-icon']) {
      let link = document.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`)
      if (!link) { link = document.createElement('link'); link.rel = rel; document.head.appendChild(link) }
      link.href = s.favicon
      link.removeAttribute('type')
    }
  }
}

// ============================================================ Carrito (vive en el navegador; precios siempre del servidor)
const CLAVE_CARRITO = 'carrito_v1'

export const useCarrito = defineStore('carrito', () => {
  const items = ref<ItemCarrito[]>(leer())
  const actualizando = ref(false)

  function leer(): ItemCarrito[] {
    try { return (JSON.parse(localStorage.getItem(CLAVE_CARRITO) || '[]') as ItemCarrito[]).filter((i) => i.codigo && i.cantidad > 0) }
    catch { return [] }
  }
  watch(items, (v) => {
    try { localStorage.setItem(CLAVE_CARRITO, JSON.stringify(v.map(({ codigo, cantidad, producto }) => ({ codigo, cantidad, producto })))) } catch { /* modo privado */ }
  }, { deep: true })

  const cantidadItems = computed(() => items.value.length)
  const unidades = computed(() => items.value.reduce((s, i) => s + i.cantidad, 0))
  const total = computed(() => items.value.reduce((s, i) => s + (i.producto?.precio ?? 0) * i.cantidad, 0))
  const sinPrecio = computed(() => items.value.filter((i) => i.producto && i.producto.precio === null))

  function cantidadDe(codigo: string): number { return items.value.find((i) => i.codigo === codigo)?.cantidad ?? 0 }

  function agregar(p: Producto, cantidad = 1): void {
    const it = items.value.find((i) => i.codigo === p.codigo)
    if (it) { it.cantidad += cantidad; it.producto = p }
    else items.value.push({ codigo: p.codigo, cantidad, producto: p })
  }
  function fijar(codigo: string, cantidad: number): void {
    const n = Math.max(0, Math.floor(cantidad))
    if (n === 0) quitar(codigo)
    else { const it = items.value.find((i) => i.codigo === codigo); if (it) it.cantidad = n }
  }
  function quitar(codigo: string): void { items.value = items.value.filter((i) => i.codigo !== codigo) }
  function vaciar(): void { items.value = [] }

  /** Trae precios/estado actuales (cambian con el login: cada cliente tiene su lista). */
  async function actualizarPrecios(): Promise<string[]> {
    if (!items.value.length) return []
    actualizando.value = true
    try {
      const r = await api.post<{ items: Producto[] }>('/carrito/precios', { codigos: items.value.map((i) => i.codigo) })
      const porCodigo = new Map(r.items.map((p) => [p.codigo, p]))
      const quitados = items.value.filter((i) => !porCodigo.has(i.codigo)).map((i) => i.producto?.nombre || i.codigo)
      items.value = items.value.filter((i) => porCodigo.has(i.codigo)).map((i) => ({ ...i, producto: porCodigo.get(i.codigo) }))
      return quitados
    } finally {
      actualizando.value = false
    }
  }

  return { items, actualizando, cantidadItems, unidades, total, sinPrecio, cantidadDe, agregar, fijar, quitar, vaciar, actualizarPrecios }
})

// ============================================================ UI
export interface Toast { id: number; mensaje: string; tipo: 'ok' | 'info' | 'error' }
let seqToast = 1

export const useUi = defineStore('ui', () => {
  const menuAbierto = ref(false)
  const carritoAbierto = ref(false)
  const toasts = ref<Toast[]>([])
  const vistaRapida = ref<string | null>(null)
  const pulsoCarrito = ref(0)

  function avisar(mensaje: string, tipo: Toast['tipo'] = 'ok', ms = 2800): void {
    const id = seqToast++
    toasts.value.push({ id, mensaje, tipo })
    setTimeout(() => cerrarToast(id), ms)
  }
  function cerrarToast(id: number): void { toasts.value = toasts.value.filter((t) => t.id !== id) }

  return { menuAbierto, carritoAbierto, toasts, vistaRapida, pulsoCarrito, avisar, cerrarToast }
})
