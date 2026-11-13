const fmtMoneda = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', minimumFractionDigits: 2, maximumFractionDigits: 2 })

export function moneda(n: number | null | undefined): string {
  return fmtMoneda.format(Number(n ?? 0))
}

/** "2026-11-12" o "2026-11-12 10:20:00" -> "12/11/2026" */
export function fecha(s: string | null | undefined, conHora = false): string {
  if (!s) return '-'
  const d = new Date(s.length <= 10 ? s + 'T00:00:00' : s.replace(' ', 'T'))
  if (isNaN(d.getTime())) return s
  return d.toLocaleString('es-AR', conHora ? { dateStyle: 'short', timeStyle: 'short' } : { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function hoyISO(): string {
  const d = new Date()
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

export function linkWhatsapp(numero: string, texto = ''): string {
  return `https://wa.me/${numero.replace(/\D/g, '')}${texto ? '?text=' + encodeURIComponent(texto) : ''}`
}

/** Celular argentino cargado "a mano" (011 15-xxxx, 11 xxxx-xxxx, +54 9 ...) a formato WhatsApp 549XXXXXXXXXX. */
export function whatsappArgentina(numero: string): string {
  let d = numero.replace(/\D/g, '').replace(/^54/, '').replace(/^9/, '').replace(/^0/, '')
  // 15 después del código de área (2 a 4 dígitos): 11 15 1234 5678 -> 11 1234 5678
  const m = d.match(/^(\d{2,4})15(\d{6,8})$/)
  if (m && (m[1]!.length + m[2]!.length) === 10) d = m[1]! + m[2]!
  return '549' + d
}

/** Párrafos de un texto plano (línea en blanco = nuevo párrafo). */
export function parrafos(texto: string): string[] {
  return texto.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean)
}

export const ESTADOS_PEDIDO = {
  pendiente: { texto: 'Recibido', clase: 'bg-amber-100 text-amber-800' },
  facturado: { texto: 'Facturado', clase: 'bg-emerald-100 text-emerald-800' },
  anulado: { texto: 'Anulado', clase: 'bg-slate-200 text-slate-600' },
} as const

/** Animación: la foto del producto "vuela" hasta el ícono del carrito visible. */
export function volarAlCarrito(origen: HTMLElement | null | undefined): void {
  if (!origen || document.documentElement.classList.contains('anim-ninguna')) return
  const destino = [...document.querySelectorAll<HTMLElement>('[data-carrito]')].find((el) => el.offsetParent !== null)
  if (!destino) return
  const a = origen.getBoundingClientRect()
  const b = destino.getBoundingClientRect()
  const clon = origen.cloneNode(true) as HTMLElement
  Object.assign(clon.style, {
    position: 'fixed', left: `${a.left}px`, top: `${a.top}px`, width: `${a.width}px`, height: `${a.height}px`,
    zIndex: '90', pointerEvents: 'none', borderRadius: '16px', objectFit: 'contain', background: 'white',
    boxShadow: '0 12px 30px rgb(0 0 0 / .25)',
  })
  document.body.appendChild(clon)
  const dx = b.left + b.width / 2 - (a.left + a.width / 2)
  const dy = b.top + b.height / 2 - (a.top + a.height / 2)
  clon.animate([
    { transform: 'translate(0,0) scale(1)', opacity: 1 },
    { transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 80}px) scale(.55) rotate(-8deg)`, opacity: 1, offset: 0.55 },
    { transform: `translate(${dx}px, ${dy}px) scale(.08) rotate(12deg)`, opacity: 0.4 },
  ], { duration: 750, easing: 'cubic-bezier(.5,0,.3,1)' }).onfinish = () => clon.remove()
}

/** Color estable (matiz) a partir de un texto: cada rubro tiene "su" color. */
export function matiz(texto: string): number {
  let h = 0
  for (const c of texto) h = (h * 31 + c.charCodeAt(0)) % 360
  return h
}
