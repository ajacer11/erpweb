import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { crearRouter } from './router'
import { alVencerSesion } from './api'
import { useSitio } from './stores'
import { vReveal } from './reveal'
import './style.css'

async function iniciar(): Promise<void> {
  const app = createApp(App)
  app.use(createPinia())
  const sitio = useSitio()
  await sitio.cargar() // config de la empresa + sesión, antes de resolver la primera ruta
  const router = crearRouter()
  alVencerSesion(() => {
    sitio.sesionVencida()
    if (router.currentRoute.value.meta.requiereLogin) router.push({ name: 'login', query: { volver: router.currentRoute.value.fullPath } })
  })
  app.directive('reveal', vReveal)
  app.use(router)
  app.mount('#app')
  document.getElementById('cargando-inicial')?.remove()
}

iniciar()
