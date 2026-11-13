import type { Directive } from 'vue'

/**
 * v-reveal: el elemento aparece (sube y se desvanece) cuando entra en pantalla.
 * v-reveal="i" escalona la entrada (i * 70 ms). Sin animaciones (configurado en el ERP) aparece directo.
 */
let observador: IntersectionObserver | null = null

function obs(): IntersectionObserver {
  observador ??= new IntersectionObserver((entradas) => {
    for (const e of entradas) {
      if (e.isIntersecting) {
        e.target.classList.add('visible')
        observador?.unobserve(e.target)
      }
    }
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 })
  return observador
}

export const vReveal: Directive<HTMLElement, number | undefined> = {
  mounted(el, binding) {
    if (document.documentElement.classList.contains('anim-ninguna') || !('IntersectionObserver' in window)) return
    el.classList.add('reveal')
    if (binding.value) el.style.transitionDelay = `${Math.min(binding.value, 12) * 70}ms`
    obs().observe(el)
  },
  unmounted(el) { observador?.unobserve(el) },
}
