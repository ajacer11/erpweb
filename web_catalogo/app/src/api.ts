/** Cliente HTTP de la API (/api, mismo dominio). */

export class ApiError extends Error {
  status: number
  codigo: string | null
  constructor(message: string, status: number, codigo: string | null = null) {
    super(message)
    this.status = status
    this.codigo = codigo
  }
}

let onSesionVencida: (() => void) | null = null
export function alVencerSesion(fn: () => void): void { onSesionVencida = fn }

async function pedir<T>(metodo: 'GET' | 'POST', ruta: string, cuerpo?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  let body: BodyInit | undefined
  if (cuerpo instanceof FormData) body = cuerpo
  else if (cuerpo !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(cuerpo) }

  let resp: Response
  try {
    resp = await fetch('/api' + ruta, { method: metodo, headers, body, credentials: 'same-origin' })
  } catch {
    throw new ApiError('No hay conexión. Revisá tu internet y probá de nuevo.', 0)
  }
  const data = await resp.json().catch(() => ({}))
  if (!resp.ok) {
    if (resp.status === 401 && data?.codigo === 'sin_sesion') onSesionVencida?.()
    throw new ApiError(data?.error || 'Ocurrió un error. Probá de nuevo.', resp.status, data?.codigo ?? null)
  }
  return data as T
}

export const api = {
  get: <T>(ruta: string, params?: Record<string, unknown>) => {
    const qs = new URLSearchParams()
    for (const [k, v] of Object.entries(params ?? {})) {
      if (v === undefined || v === null || v === '') continue
      if (typeof v === 'object') for (const [k2, v2] of Object.entries(v as Record<string, unknown>)) { if (v2 !== '' && v2 != null) qs.append(`${k}[${k2}]`, String(v2)) }
      else qs.append(k, String(v))
    }
    const s = qs.toString()
    return pedir<T>('GET', ruta + (s ? '?' + s : ''))
  },
  post: <T>(ruta: string, cuerpo?: unknown) => pedir<T>('POST', ruta, cuerpo ?? {}),
}
