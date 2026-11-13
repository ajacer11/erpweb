<script setup lang="ts">
/**
 * Ícono animado para un rubro. Se elige por palabras clave del nombre (sirve para cualquier empresa).
 * Animación suave siempre; más marcada cuando el contenedor tiene la clase "group" y está en hover.
 */
import { computed } from 'vue'

const props = withDefaults(defineProps<{ nombre: string; size?: number; tipo?: string }>(), { size: 56, tipo: '' })

const REGLAS: [RegExp, string][] = [
  // El orden importa: gana la primera que coincide.
  [/repuesto/i, 'herramienta'],
  [/mobiliario|silla|mesa|banqu|reposer|catre|relax|hogar/i, 'silla'],
  [/reel|carrete|rotativ/i, 'reel'],
  [/ca[ñn]a|puntera|mosca|posaca/i, 'cana'],
  [/anzuel|ganch|plomad|plomo|emerillon|destorcedor|esmerillon/i, 'anzuelo'],
  [/se[ñn]uel|cuchar|artificial|jig|spinner|lure|pez art/i, 'senuelo'],
  [/l[ií]nea|nylon|tanza|multifil|hilo|monofil|l[ií]der|cable|acero/i, 'linea'],
  [/boya|flota|corcho|n[aá]utic|salvavid|chaleco/i, 'boya'],
  [/carpa|camping|toldo|gazebo|refugio/i, 'carpa'],
  [/linterna|luz|l[aá]mpara|farol|ilumin|vincha/i, 'linterna'],
  [/cuchill|naval|cortaplum|machete|hacha|multiherr/i, 'cuchillo'],
  [/arma|rifle|aire compr|pcp|bal[ií]n|mira|tiro|munici|pistol|cartuch|airsoft|defensa|[oó]ptic|binocul|largavist/i, 'mira'],
  [/indument|ropa|campera|remera|pantal|gorr|calzad|bota|guante|vadeador|piloto/i, 'ropa'],
  [/dormir|colch|aislante|almohad|sobre/i, 'dormir'],
  [/conserv|heladera|hielo|t[eé]rmic|termo/i, 'frio'],
  [/mochil|bolso|bolsa|estuche|porta|organiz|funda/i, 'mochila'],
  [/balanz|peso/i, 'balanza'],
  [/gas|calentad|anafe|parrill|fuego|garrafa|encended|soplete|cocina|superviv/i, 'fuego'],
  [/\bred(es)?\b|medio ?mundo|copo|tramp|rastr|vivero/i, 'red'],
  [/aceite|carnad|atray|cebo|engodo|masa|lubric|hidrat|balde|recipiente|bid[oó]n|botell|cantimplor/i, 'gota'],
  [/pila|bater|cargad|solar|electr/i, 'bateria'],
  [/aire libre|trek|brujul|navega|outdoor|aventura/i, 'brujula'],
  [/pesca|pez|pescad/i, 'pez'],
  [/herram|pinza|alicate|accesor/i, 'herramienta'],
]

const TIPOS = new Set(['cana', 'reel', 'anzuelo', 'senuelo', 'linea', 'boya', 'carpa', 'linterna', 'cuchillo', 'mira', 'ropa', 'dormir', 'frio',
  'silla', 'mochila', 'balanza', 'fuego', 'red', 'gota', 'bateria', 'brujula', 'herramienta', 'pez', 'caja'])
/** Ícono elegido en el ERP para el rubro, o el que corresponde por palabras clave. */
const tipo = computed(() => (props.tipo && TIPOS.has(props.tipo) ? props.tipo : REGLAS.find(([re]) => re.test(props.nombre))?.[1] ?? 'caja'))
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.6"
       stroke-linecap="round" stroke-linejoin="round" class="ri" :class="`ri-${tipo}`" aria-hidden="true">
    <!-- Caña: la puntera se mueve y la boya flota -->
    <g v-if="tipo === 'cana'">
      <g class="a-sway o-bl"><path d="M8 56 L44 12" /><circle cx="16" cy="46" r="4" /><path d="M13 49l-3 3" /></g>
      <path class="a-dash" d="M44 12 Q52 26 50 40" stroke-dasharray="3 4" stroke-width="1.6" />
      <g class="a-bob"><path d="M50 40 v3" /><ellipse cx="50" cy="47" rx="3.2" ry="4.4" fill="currentColor" fill-opacity=".25" /></g>
      <path class="a-ripple" d="M42 54 q8 -4 16 0" stroke-width="1.6" />
    </g>
    <!-- Reel: la bobina gira -->
    <g v-else-if="tipo === 'reel'">
      <path d="M14 14 h20 v8" /><path d="M24 14 v-4" />
      <g class="a-spin o-c" style="transform-origin: 32px 38px"><circle cx="32" cy="38" r="14" /><circle cx="32" cy="38" r="4" fill="currentColor" fill-opacity=".3" /><path d="M32 24 v6 M32 46 v6 M18 38 h6 M40 38 h6" /></g>
      <path d="M46 38 h8 v6" /><circle cx="54" cy="47" r="3" fill="currentColor" fill-opacity=".3" />
    </g>
    <!-- Anzuelo: se balancea desde el ojal -->
    <g v-else-if="tipo === 'anzuelo'">
      <path class="a-dash" d="M32 2 v8" stroke-dasharray="2 3" />
      <g class="a-swing" style="transform-origin: 32px 12px"><circle cx="32" cy="13" r="3" /><path d="M32 16 v26 a10 10 0 0 1 -20 0 v-4" /><path d="M12 38 l-4 6 M12 38 l6 4" /></g>
    </g>
    <!-- Señuelo: nada y suelta burbujas -->
    <g v-else-if="tipo === 'senuelo'">
      <g class="a-wiggle o-c" style="transform-origin: 30px 32px">
        <path d="M10 32 Q24 18 44 26 Q50 32 44 38 Q24 46 10 32 Z" fill="currentColor" fill-opacity=".18" />
        <circle cx="40" cy="30" r="2" fill="currentColor" /><path d="M10 32 l-4 -6 M10 32 l-4 6" /><path d="M22 38 l-2 6 l4 0 M32 38 l-1 6 l4 0" stroke-width="1.8" />
      </g>
      <circle class="a-bubble b1" cx="52" cy="22" r="2.2" /><circle class="a-bubble b2" cx="56" cy="28" r="1.6" /><circle class="a-bubble b3" cx="50" cy="30" r="1.2" />
    </g>
    <!-- Línea: la bobina suelta hilo -->
    <g v-else-if="tipo === 'linea'">
      <rect x="10" y="16" width="26" height="32" rx="4" /><path d="M10 24 h26 M10 40 h26" />
      <g class="a-spin-slow o-c" style="transform-origin: 23px 32px"><path d="M14 28 q9 4 18 0 M14 33 q9 4 18 0 M14 38 q9 -4 18 0" stroke-width="1.6" /></g>
      <path class="a-flow" d="M36 30 C 46 30 44 46 56 46" stroke-dasharray="4 4" stroke-width="1.8" />
    </g>
    <!-- Boya: flota con ondas -->
    <g v-else-if="tipo === 'boya'">
      <g class="a-bob"><path d="M32 6 v10" /><path d="M24 30 a8 12 0 0 1 16 0 a8 10 0 0 1 -16 0 Z" fill="currentColor" fill-opacity=".25" /><path d="M24 30 h16" /><path d="M32 40 v6" /></g>
      <path class="a-ripple" d="M14 50 q18 -6 36 0" /><path class="a-ripple r2" d="M8 56 q24 -6 48 0" stroke-width="1.6" />
    </g>
    <!-- Carpa: el banderín flamea y titilan estrellas -->
    <g v-else-if="tipo === 'carpa'">
      <path d="M6 54 L32 18 L58 54 Z" fill="currentColor" fill-opacity=".14" /><path d="M32 18 L24 54 M32 18 L40 54" /><path d="M2 54 h60" />
      <path d="M32 18 v-10" /><path class="a-flag o-l" d="M32 8 q6 2 10 0 v6 q-4 2 -10 0" fill="currentColor" fill-opacity=".35" style="transform-origin: 32px 11px" />
      <path class="a-twinkle" d="M50 10 l1 3 3 1 -3 1 -1 3 -1 -3 -3 -1 3 -1z" fill="currentColor" stroke-width="1" />
      <path class="a-twinkle t2" d="M12 14 l.8 2 2 .8 -2 .8 -.8 2 -.8 -2 -2 -.8 2 -.8z" fill="currentColor" stroke-width="1" />
    </g>
    <!-- Linterna: el haz late -->
    <g v-else-if="tipo === 'linterna'">
      <g transform="rotate(-30 32 32)">
        <rect x="8" y="27" width="22" height="10" rx="3" /><path d="M30 25 h8 v14 h-8 z" fill="currentColor" fill-opacity=".25" /><path d="M14 32 h6" />
        <path class="a-beam" d="M38 26 L60 16 L60 48 L38 38 Z" fill="currentColor" fill-opacity=".22" stroke-width="0" />
        <path class="a-beam" d="M38 26 L60 16 M38 38 L60 48" stroke-width="1.6" />
      </g>
    </g>
    <!-- Cuchillo: brillo que recorre la hoja -->
    <g v-else-if="tipo === 'cuchillo'">
      <defs><clipPath id="ri-hoja"><path d="M22 36 L54 8 Q58 18 46 30 L28 42 Z" /></clipPath></defs>
      <path d="M22 36 L54 8 Q58 18 46 30 L28 42 Z" fill="currentColor" fill-opacity=".15" />
      <g clip-path="url(#ri-hoja)"><rect class="a-shine" x="10" y="0" width="8" height="70" fill="white" fill-opacity=".85" stroke-width="0" transform="rotate(25 32 32)" /></g>
      <path d="M22 36 L10 48 Q8 52 12 54 Q16 56 18 52 L28 42" fill="currentColor" fill-opacity=".35" /><path d="M20 34 l10 10" />
    </g>
    <!-- Mira: gira y late -->
    <g v-else-if="tipo === 'mira'">
      <g class="a-spin-slow o-c" style="transform-origin: 32px 32px"><circle cx="32" cy="32" r="20" stroke-dasharray="10 4" /></g>
      <circle class="a-pulse o-c" cx="32" cy="32" r="11" style="transform-origin: 32px 32px" /><path d="M32 4 v14 M32 46 v14 M4 32 h14 M46 32 h14" />
      <circle cx="32" cy="32" r="2.5" fill="currentColor" />
    </g>
    <!-- Ropa: se balancea en la percha -->
    <g v-else-if="tipo === 'ropa'">
      <g class="a-swing" style="transform-origin: 32px 6px">
        <path d="M32 6 v4" /><path d="M24 14 Q32 20 40 14 L54 22 L48 32 L44 30 V56 H20 V30 L16 32 L10 22 Z" fill="currentColor" fill-opacity=".16" />
      </g>
    </g>
    <!-- Dormir: luna y zetas que suben -->
    <g v-else-if="tipo === 'dormir'">
      <path d="M6 48 q0 -12 14 -12 h30 q8 0 8 8 v8 h-52 z" fill="currentColor" fill-opacity=".16" /><path d="M6 52 h52" /><circle cx="18" cy="42" r="4" />
      <path d="M44 10 a10 10 0 1 0 10 14 a8 8 0 0 1 -10 -14 z" fill="currentColor" fill-opacity=".3" />
      <text class="a-zz" x="30" y="24" font-size="9" font-weight="800" fill="currentColor" stroke="none">z</text>
      <text class="a-zz z2" x="36" y="18" font-size="7" font-weight="800" fill="currentColor" stroke="none">z</text>
    </g>
    <!-- Frío: copo que gira sobre la conservadora -->
    <g v-else-if="tipo === 'frio'">
      <rect x="8" y="30" width="48" height="26" rx="4" fill="currentColor" fill-opacity=".14" /><path d="M8 38 h48 M26 26 h12 v4" />
      <g class="a-spin-slow o-c" style="transform-origin: 32px 14px"><path d="M32 4 v20 M23 9 l18 10 M41 9 l-18 10" stroke-width="2" /></g>
    </g>
    <!-- Silla: se mece -->
    <g v-else-if="tipo === 'silla'">
      <g class="a-rock" style="transform-origin: 32px 56px">
        <path d="M16 10 l6 26 h22 l4 -26" /><path d="M14 36 h36 l-4 8 h-28 z" fill="currentColor" fill-opacity=".2" /><path d="M20 44 l-6 12 M44 44 l6 12 M22 18 h22" />
      </g>
    </g>
    <!-- Mochila: rebota -->
    <g v-else-if="tipo === 'mochila'">
      <g class="a-bounce">
        <path d="M18 20 q0 -10 14 -10 q14 0 14 10 v32 q0 4 -4 4 h-20 q-4 0 -4 -4 z" fill="currentColor" fill-opacity=".16" />
        <path d="M26 10 q6 -6 12 0" /><rect x="22" y="34" width="20" height="12" rx="3" /><path d="M22 26 h20" />
      </g>
      <path class="a-shadow" d="M20 60 h24" stroke-width="2" />
    </g>
    <!-- Balanza: se inclina -->
    <g v-else-if="tipo === 'balanza'">
      <path d="M32 8 v46 M20 56 h24" />
      <g class="a-tilt" style="transform-origin: 32px 14px">
        <path d="M12 14 h40" /><path d="M14 14 l-6 18 h12 z M50 14 l-6 18 h12 z" fill="currentColor" fill-opacity=".2" />
      </g>
    </g>
    <!-- Fuego: titila -->
    <g v-else-if="tipo === 'fuego'">
      <g class="a-flicker o-b" style="transform-origin: 32px 50px">
        <path d="M32 6 C 44 20 48 30 44 40 C 42 48 36 52 32 52 C 24 52 18 46 18 38 C 18 30 24 26 26 18 C 30 24 30 28 32 30 C 34 22 32 14 32 6 Z" fill="currentColor" fill-opacity=".25" />
        <path d="M32 52 c-5 0 -7 -4 -7 -8 c0 -5 5 -7 6 -12 c4 5 8 8 8 12 c0 5 -3 8 -7 8 z" fill="currentColor" fill-opacity=".45" />
      </g>
      <path d="M14 58 h36" />
    </g>
    <!-- Red: la malla ondula -->
    <g v-else-if="tipo === 'red'">
      <path d="M6 58 L30 30" /><circle cx="40" cy="22" r="14" />
      <g class="a-wave o-t" style="transform-origin: 40px 22px"><path d="M30 14 l20 16 M28 22 l22 0 M30 30 l20 -16 M40 8 v28" stroke-width="1.4" /></g>
    </g>
    <!-- Gota: cae y salpica -->
    <g v-else-if="tipo === 'gota'">
      <path d="M18 8 h16 v6 h-16 z M22 14 h8 v10 q8 4 8 14 v14 q0 4 -4 4 h-16 q-4 0 -4 -4 v-14 q0 -10 8 -14 z" fill="currentColor" fill-opacity=".14" />
      <path class="a-drop" d="M48 20 q5 7 0 10 q-5 -3 0 -10 z" fill="currentColor" fill-opacity=".5" />
      <path class="a-ripple" d="M40 56 q8 -4 16 0" stroke-width="1.6" />
    </g>
    <!-- Batería: se carga -->
    <g v-else-if="tipo === 'bateria'">
      <rect x="8" y="20" width="44" height="24" rx="4" /><path d="M52 28 h4 v8 h-4" />
      <rect class="a-charge" x="12" y="24" width="36" height="16" rx="2" fill="currentColor" fill-opacity=".35" stroke-width="0" />
      <path class="a-twinkle" d="M32 22 l-6 11 h6 l-3 9 l9 -12 h-6 l3 -8 z" fill="currentColor" stroke-width="1" />
    </g>
    <!-- Brújula: la aguja busca el norte -->
    <g v-else-if="tipo === 'brujula'">
      <circle cx="32" cy="32" r="24" /><circle cx="32" cy="32" r="18" stroke-dasharray="2 5" stroke-width="1.6" />
      <g class="a-needle" style="transform-origin: 32px 32px"><path d="M32 14 l6 18 l-6 18 l-6 -18 z" fill="currentColor" fill-opacity=".25" /><path d="M32 14 l6 18 h-12 z" fill="currentColor" /></g>
    </g>
    <!-- Herramienta: la llave gira -->
    <g v-else-if="tipo === 'herramienta'">
      <g class="a-turn" style="transform-origin: 32px 32px">
        <path d="M40 8 a10 10 0 0 0 -8 14 L10 44 a4 4 0 0 0 0 6 l4 4 a4 4 0 0 0 6 0 L42 32 a10 10 0 0 0 14 -8 l-7 2 l-5 -5 l2 -7 z" fill="currentColor" fill-opacity=".18" />
      </g>
      <path class="a-twinkle" d="M52 44 l1.5 4 4 1.5 -4 1.5 -1.5 4 -1.5 -4 -4 -1.5 4 -1.5z" fill="currentColor" stroke-width="1" />
    </g>
    <!-- Pez: nada -->
    <g v-else-if="tipo === 'pez'">
      <g class="a-swim">
        <path d="M8 32 Q24 14 44 30 Q24 50 8 32 Z" fill="currentColor" fill-opacity=".2" /><path d="M44 30 L56 20 L54 32 L56 44 Z" fill="currentColor" fill-opacity=".35" />
        <circle cx="18" cy="29" r="2" fill="currentColor" /><path d="M26 24 q4 8 0 16" stroke-width="1.6" />
      </g>
      <circle class="a-bubble b1" cx="10" cy="18" r="2" /><circle class="a-bubble b2" cx="6" cy="24" r="1.4" />
    </g>
    <!-- Genérico: caja con destellos -->
    <g v-else>
      <g class="a-bounce"><path d="M32 8 L54 18 V44 L32 56 L10 44 V18 Z" fill="currentColor" fill-opacity=".16" /><path d="M10 18 L32 30 L54 18 M32 30 V56" /></g>
      <path class="a-twinkle" d="M54 6 l1.5 4 4 1.5 -4 1.5 -1.5 4 -1.5 -4 -4 -1.5 4 -1.5z" fill="currentColor" stroke-width="1" />
      <path class="a-twinkle t2" d="M8 50 l1 3 3 1 -3 1 -1 3 -1 -3 -3 -1 3 -1z" fill="currentColor" stroke-width="1" />
    </g>
  </svg>
</template>

<style scoped>
.ri { overflow: visible; }
.ri * { transform-box: view-box; }
.o-c { transform-box: view-box; }

/* Animaciones base: suaves siempre, más rápidas con hover del contenedor (.group) */
.a-sway { animation: sway 3.2s ease-in-out infinite; transform-origin: 8px 56px; }
.a-bob { animation: bob 2.4s ease-in-out infinite; }
.a-ripple { animation: ripple 2.4s ease-out infinite; transform-origin: center; opacity: .6; }
.a-ripple.r2 { animation-delay: .8s; }
.a-dash { animation: dash 1.6s linear infinite; }
.a-flow { animation: dash 1s linear infinite; }
.a-spin { animation: spin 3s linear infinite; }
.a-spin-slow { animation: spin 9s linear infinite; }
.a-swing { animation: swing 2.6s ease-in-out infinite; }
.a-wiggle { animation: wiggle 1.4s ease-in-out infinite; }
.a-bubble { animation: bubble 2.6s ease-in infinite; opacity: 0; stroke-width: 1.4; }
.a-bubble.b2 { animation-delay: .9s; } .a-bubble.b3 { animation-delay: 1.6s; }
.a-flag { animation: flag 1.2s ease-in-out infinite; }
.a-twinkle { animation: twinkle 2s ease-in-out infinite; transform-origin: center; }
.a-twinkle.t2 { animation-delay: 1s; }
.a-beam { animation: beam 1.8s ease-in-out infinite; }
.a-shine { animation: shine 2.8s ease-in-out infinite; }
.a-pulse { animation: pulse 1.8s ease-in-out infinite; }
.a-zz { animation: zz 2.8s ease-in infinite; opacity: 0; }
.a-zz.z2 { animation-delay: 1.3s; }
.a-rock { animation: rock 2.8s ease-in-out infinite; }
.a-bounce { animation: bounce 1.6s cubic-bezier(.5,0,.5,1) infinite; }
.a-shadow { animation: shadow 1.6s cubic-bezier(.5,0,.5,1) infinite; transform-origin: 32px 60px; opacity: .4; }
.a-tilt { animation: tilt 3s ease-in-out infinite; }
.a-flicker { animation: flicker 1.1s ease-in-out infinite alternate; }
.a-wave { animation: wave 2.2s ease-in-out infinite; }
.a-drop { animation: drop 1.8s ease-in infinite; }
.a-charge { animation: charge 2.4s ease-in-out infinite; transform-origin: 12px 32px; }
.a-needle { animation: needle 3.6s ease-in-out infinite; }
.a-turn { animation: turn 3s ease-in-out infinite; }
.a-swim { animation: swim 2.4s ease-in-out infinite; }

:global(.group:hover) .ri * { animation-duration: .9s; }
:global(.group:hover) .a-spin-slow, :global(.group:hover) .a-needle { animation-duration: 2s; }

@keyframes sway { 0%,100% { transform: rotate(0deg); } 50% { transform: rotate(-5deg); } }
@keyframes bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(4px); } }
@keyframes ripple { 0% { transform: scaleX(.6); opacity: .7; } 100% { transform: scaleX(1.15); opacity: 0; } }
@keyframes dash { to { stroke-dashoffset: -14; } }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes swing { 0%,100% { transform: rotate(10deg); } 50% { transform: rotate(-10deg); } }
@keyframes wiggle { 0%,100% { transform: rotate(-6deg) translateX(-2px); } 50% { transform: rotate(6deg) translateX(2px); } }
@keyframes bubble { 0% { transform: translateY(8px); opacity: 0; } 30% { opacity: .9; } 100% { transform: translateY(-16px); opacity: 0; } }
@keyframes flag { 0%,100% { transform: skewY(0deg) scaleX(1); } 50% { transform: skewY(-8deg) scaleX(.9); } }
@keyframes twinkle { 0%,100% { transform: scale(.4); opacity: .2; } 50% { transform: scale(1.1); opacity: 1; } }
@keyframes beam { 0%,100% { opacity: .35; } 50% { opacity: 1; } }
@keyframes shine { 0% { transform: translateX(-30px) rotate(25deg); } 60%,100% { transform: translateX(60px) rotate(25deg); } }
@keyframes pulse { 0%,100% { transform: scale(.85); opacity: .5; } 50% { transform: scale(1.1); opacity: 1; } }
@keyframes zz { 0% { transform: translate(0, 6px); opacity: 0; } 30% { opacity: 1; } 100% { transform: translate(8px, -10px); opacity: 0; } }
@keyframes rock { 0%,100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }
@keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
@keyframes shadow { 0%,100% { transform: scaleX(1); } 50% { transform: scaleX(.6); } }
@keyframes tilt { 0%,100% { transform: rotate(-8deg); } 50% { transform: rotate(8deg); } }
@keyframes flicker { 0% { transform: scale(1, 1) skewX(0deg); } 50% { transform: scale(.94, 1.06) skewX(3deg); } 100% { transform: scale(1.04, .95) skewX(-3deg); } }
@keyframes wave { 0%,100% { transform: scale(1) rotate(0deg); } 50% { transform: scale(.92, 1.06) rotate(6deg); } }
@keyframes drop { 0% { transform: translateY(-6px); opacity: 0; } 20% { opacity: 1; } 100% { transform: translateY(28px); opacity: 0; } }
@keyframes charge { 0% { transform: scaleX(.1); } 70%,100% { transform: scaleX(1); } }
@keyframes needle { 0%,100% { transform: rotate(-25deg); } 30% { transform: rotate(30deg); } 60% { transform: rotate(-10deg); } 80% { transform: rotate(5deg); } }
@keyframes turn { 0%,100% { transform: rotate(-15deg); } 50% { transform: rotate(20deg); } }
@keyframes swim { 0%,100% { transform: translateX(-3px) rotate(-3deg); } 50% { transform: translateX(4px) rotate(3deg); } }

/* Nivel de animación configurado en el ERP (clase en <html>) */
:global(.anim-suaves) .ri * { animation-play-state: paused; }
:global(.anim-suaves .group:hover) .ri *, :global(.anim-suaves .group:focus-visible) .ri * { animation-play-state: running; }
:global(.anim-ninguna) .ri * { animation: none !important; }
@media (prefers-reduced-motion: reduce) { .ri * { animation: none !important; } }
</style>
