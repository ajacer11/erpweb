export interface Banner {
  imagen: string
  titulo: string
  subtitulo: string
  botonTexto: string
  botonLink: string
}

export interface Sitio {
  nombre: string
  slogan: string
  descripcion: string
  logo: string | null
  favicon: string | null
  banners: Banner[]
  quienesSomos: { titulo: string; texto: string; imagen: string | null }
  contacto: {
    telefono: string
    whatsapp: string
    email: string
    direccion: string
    localidad: string
    horarios: string
    mapa: string
    whatsappFlotante: boolean
  }
  redes: Partial<Record<'instagram' | 'facebook' | 'tiktok' | 'youtube', string>>
  colores: { primario: string; secundario: string; encabezado: string }
  catalogo: { publico: boolean; preciosPublicos: boolean; precioConIva: boolean; mostrarStock: 'ninguno' | 'disponibilidad' | 'cantidad' }
  pedidos: { habilitados: boolean; montoMinimo: number }
  registroHabilitado: boolean
  avisosPagoHabilitados: boolean
  diseno: Diseno
  cliente: Cliente | null
}

export type SeccionPortada = 'beneficios' | 'rubros' | 'destacados' | 'novedades' | 'franja' | 'nosotros' | 'contacto'

export interface Diseno {
  animaciones: 'completas' | 'suaves' | 'ninguna'
  encabezado: 'claro' | 'oscuro'
  anuncio: string
  heroBuscador: boolean
  heroOlas: boolean
  heroPalabras: string[]
  contadores: boolean
  beneficios: { icono: string; titulo: string; texto: string }[]
  secciones: SeccionPortada[]
  rubrosIconos: Record<string, string>
  carritoSinLogin: boolean
  vistaRapida: boolean
}

export interface Cliente {
  razon: string
  usuario: string
}

export interface Producto {
  codigo: string
  nombre: string
  rubro: string | null
  familia: { id: number; nombre: string } | null
  marca: { id: number; nombre: string } | null
  precio: number | null
  precioNeto: number | null
  alicuotaIva: number
  stock: { disponible: boolean | null; cantidad?: number }
  bulto: number
  imagen: string | null
  nuevo: boolean
}

export interface ProductoDetalle extends Producto {
  descripcion: string
  fotos: string[]
  caracteristicas: { nombre: string; valor: string }[]
  relacionados: Producto[]
}

export interface Pagina<T> {
  items: T[]
  total: number
  pagina: number
  porPagina: number
  paginas: number
}

export interface FiltroItem { id?: number; nombre: string; cantidad: number }
export interface Filtros {
  rubros: FiltroItem[]
  familias: FiltroItem[]
  marcas: FiltroItem[]
  atributos: { id: number; nombre: string; unidad: string | null; valores: { valor: string; cantidad: number }[] }[]
}

export interface Inicio {
  destacados: Producto[]
  novedades: Producto[]
  rubros: { nombre: string; cantidad: number; imagen: string | null }[]
  familias: { id: number; nombre: string; cantidad: number }[]
  contadores: { productos: number; rubros: number; novedades: number }
}

export interface ItemCarrito {
  codigo: string
  cantidad: number
  producto?: Producto
}

export type EstadoPedido = 'pendiente' | 'facturado' | 'anulado'

export interface ResumenCuenta {
  datos: Record<string, string | null>
  saldo: number
  pendientes: { cantidad: number; total: number; vencido: number }
  ultimosPedidos: { numero: number; fecha: string; estado: EstadoPedido; total: number }[]
  datosPago: string
}

export interface Movimiento {
  fecha: string
  concepto: string
  numero: string
  debe: number
  haber: number
  saldo: number
  comprobanteId: number | null
}

export interface Comprobante {
  id: number
  tipo: string
  numero: string
  fecha: string
  vencimiento: string | null
  vencida?: boolean
  total: number
  saldo: number
}

export interface PedidoResumen {
  numero: number
  fecha: string
  estado: EstadoPedido
  web: boolean
  total: number
  items: number
  observaciones: string
}

export interface PedidoDetalle {
  numero: number
  fecha: string
  estado: EstadoPedido
  observaciones: string
  lineas: { codigo: string; descripcion: string; cantidad: number; precio: number; subtotal: number }[]
  total: number
}

export interface AvisoPago {
  id: number
  fecha: string
  fecha_pago: string
  importe: number
  medio: string
  referencia: string | null
  observaciones: string | null
  estado: 'pendiente' | 'procesado' | 'rechazado'
  adjunto: boolean
}
