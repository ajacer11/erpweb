import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useSitio } from './stores'

declare module 'vue-router' {
  interface RouteMeta { titulo?: string; requiereLogin?: boolean; soloInvitado?: boolean }
}

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'inicio', component: () => import('./views/InicioView.vue') },
  { path: '/catalogo', name: 'catalogo', component: () => import('./views/CatalogoView.vue'), meta: { titulo: 'Catálogo' } },
  { path: '/producto/:codigo(.*)', name: 'producto', component: () => import('./views/ProductoView.vue') },
  { path: '/pedido', name: 'pedido', component: () => import('./views/PedidoView.vue'), meta: { titulo: 'Mi pedido' } },
  { path: '/nosotros', name: 'nosotros', component: () => import('./views/NosotrosView.vue') },
  { path: '/contacto', name: 'contacto', component: () => import('./views/ContactoView.vue'), meta: { titulo: 'Contacto' } },
  { path: '/imprimir', name: 'imprimir', component: () => import('./views/ImprimirView.vue'), meta: { titulo: 'Lista de productos' } },

  { path: '/ingresar', name: 'login', component: () => import('./views/LoginView.vue'), meta: { titulo: 'Ingresar', soloInvitado: true } },
  { path: '/recuperar', name: 'recuperar', component: () => import('./views/RecuperarView.vue'), meta: { titulo: 'Recuperar contraseña', soloInvitado: true } },
  { path: '/registro', name: 'registro', component: () => import('./views/RegistroView.vue'), meta: { titulo: 'Quiero ser cliente', soloInvitado: true } },
  { path: '/cuenta/activar', name: 'activar', component: () => import('./views/ActivarView.vue'), meta: { titulo: 'Crear contraseña' } },

  {
    path: '/cuenta',
    component: () => import('./views/cuenta/CuentaLayout.vue'),
    meta: { requiereLogin: true },
    children: [
      { path: '', name: 'cuenta', component: () => import('./views/cuenta/ResumenView.vue'), meta: { titulo: 'Mi cuenta', requiereLogin: true } },
      { path: 'movimientos', name: 'movimientos', component: () => import('./views/cuenta/MovimientosView.vue'), meta: { titulo: 'Cuenta corriente', requiereLogin: true } },
      { path: 'comprobantes', name: 'comprobantes', component: () => import('./views/cuenta/ComprobantesView.vue'), meta: { titulo: 'Facturas', requiereLogin: true } },
      { path: 'pedidos', name: 'pedidos', component: () => import('./views/cuenta/PedidosView.vue'), meta: { titulo: 'Mis pedidos', requiereLogin: true } },
      { path: 'pedidos/:numero(\\d+)', name: 'pedido-detalle', component: () => import('./views/cuenta/PedidoDetalleView.vue'), meta: { titulo: 'Pedido', requiereLogin: true } },
      { path: 'pagos', name: 'pagos', component: () => import('./views/cuenta/PagosView.vue'), meta: { titulo: 'Informar un pago', requiereLogin: true } },
      { path: 'datos', name: 'datos', component: () => import('./views/cuenta/DatosView.vue'), meta: { titulo: 'Mis datos', requiereLogin: true } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'no-encontrada', component: () => import('./views/NoEncontradaView.vue'), meta: { titulo: 'Página no encontrada' } },
]

export function crearRouter() {
  const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, saved) {
      if (saved) return saved
      if (to.name === from.name && to.name === 'catalogo' && to.query.pagina === from.query.pagina) return undefined
      return { top: 0 }
    },
  })

  router.beforeEach((to) => {
    const sitio = useSitio()
    if (to.meta.requiereLogin && !sitio.logueado) return { name: 'login', query: { volver: to.fullPath } }
    if (to.meta.soloInvitado && sitio.logueado) return { name: 'cuenta' }
    return true
  })

  router.afterEach((to) => {
    const nombre = useSitio().sitio?.nombre ?? ''
    document.title = to.meta.titulo ? `${to.meta.titulo} · ${nombre}` : nombre
  })

  return router
}
