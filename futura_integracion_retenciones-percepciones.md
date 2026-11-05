# Ser agente de retención / percepción de IVA y Ganancias (diseño, sin implementar)

Estado (2026-10-15): **implementado en versión genérica configurable**, ver "Lo implementado" abajo. El resto del documento queda como referencia del diseño original.

## Lo implementado (2026-10-15)

- Regímenes configurables para cualquier impuesto y jurisdicción (IVA, Ganancias, IIBB, otros), no solo IVA/Ganancias: `regimenes_retper` (sql/2026-10-15_retenciones_percepciones.sql). Un régimen habilitado es la "bandera" de agente; sin ninguno, nada cambia.
- Alícuota por sujeto: excepción manual (`sujetos_retper`), padrón importable por CUIT (`padron_retper`, CSV/Excel), condición fiscal y alícuota para no inscriptos. Mínimo no sujeto, "sobre el excedente" y acumulado mensual.
- Retención en pagos a proveedores (`api_pagos_proveedores.php` + `proveedores_pagar_facturas.php`): cálculo en el servidor, certificado imprimible (`imprimir_certificado_retencion.php`), anulación con el pago.
- Percepción en facturación (`api_facturar.php`): se suma al total, viaja a ARCA como Tributos (`ARCA/ArcaFunctions.php`), asiento, comprobante impreso y Libro IVA. Antes de pedir CAE con percepciones se consulta al servidor de facturación si admite percepciones (`new_fe_cae_api.php?capacidades=1`).
- Pantallas: `retper_regimenes.php` (configuración) y `retper_practicadas.php` (informe + Excel). Lógica en `retper_helpers.php`.

**No implementado** (sigue pendiente de este diseño): consulta en vivo al Padrón de ARCA (`ws_sr_padron_a13`) y alerta de condición fiscal desactualizada; archivos de presentación SIRE/SICORE/ATM (hoy: Excel con todos los datos); escalas progresivas de Ganancias; retención sobre cobros.

Se planea ofrecer como módulo adicional (no incluido por defecto en ninguna licencia) — ver el mecanismo de habilitación en `futura_arquitectura_modulos_adicionales.md`.

## Alcance pedido

Que el sistema contemple el caso en que la empresa (el tenant) es **agente de retención y/o de percepción** de IVA y/o Ganancias ante AFIP/ARCA, aplicando lo que corresponda al pagar a proveedores y al cobrar/facturar a clientes.

## Condición previa: la empresa se autodefine como agente

No todas las empresas son agentes de retención/percepción — AFIP designa esa condición puntualmente (por resolución, generalmente a "grandes contribuyentes" o por régimen específico), y no todos los tenants del sistema la van a tener. Por eso **todo este mecanismo (consulta al Padrón, cálculo, certificado) solo se activa si la propia empresa lo configura explícitamente**, en vez de intentar correr siempre "por las dudas".

- Se agregan 4 banderas independientes en Configuración (no una sola): "Soy agente de retención de IVA", "Soy agente de retención de Ganancias", "Soy agente de percepción de IVA", "Soy agente de percepción de Ganancias" — independientes porque AFIP puede designar a una empresa para un régimen y no para otro.
- Si **ninguna** bandera está activa, el sistema no consulta el Padrón nunca, no calcula nada, no le agrega fricción a pagar un proveedor o cobrar un cliente — se comporta exactamente igual que hoy.
- Si hay alguna activa, cada flujo (retención en pagos, percepción en ventas) solo corre para los impuestos marcados — ej. si solo está tildado "agente de retención de IVA", pagar a un proveedor dispara el chequeo de IVA pero no el de Ganancias.
- Guardar también, junto a cada bandera, la fecha de designación y el número de resolución de AFIP que la origina — es un dato de referencia útil para auditoría, no algo que el sistema use para decidir nada.

## Estado actual del sistema (relevado antes de diseñar)

Ya existen campos `retiva` / `retgan` / `retiibb` en el código, pero **no son este caso** — son escenarios distintos, ya construidos:

- **`cobrar_facturas.php` / `api_cobros.php`**: registro manual de lo que el **cliente** le retiene a la empresa cuando le paga (la empresa es la retenida, no la agente).
- **Carga de facturas de compra** (`proveedores_facturas_add.php`, campos `t-perciva` / `t-perciibb`): son **percepciones** que el **proveedor** le cobra a la empresa en su propia factura (el proveedor es el agente, la empresa es la percibida).
- **`proveedores_pagar_facturas.php`** (pantalla real de pago a proveedores): no tiene nada de retención/percepción hoy.

Conclusión: el caso "la empresa retiene/percibe a otros" **no existe hoy**, es funcionalidad nueva.

## Distinción clave: Retención vs. Percepción

Son espejo una de otra, y **no comparten el mismo punto de disparo ni la misma forma de reportarse a ARCA**:

| | Retención | Percepción |
|---|---|---|
| ¿Cuándo se aplica? | Al **pagar** (ej. a un proveedor) | Al **cobrar/facturar** (ej. a un cliente) |
| ¿Quién "pierde" plata en el momento? | La contraparte (se le paga de menos) | La contraparte (se le cobra de más) |
| ¿Toca la facturación electrónica (WSFE/CAE)? | **No** — es un proceso totalmente aparte | **Sí** — va como campo `Tributos` dentro del mismo llamado a ARCA que ya se hace para pedir el CAE en `api_facturar.php`, no es una llamada nueva |
| ¿Cómo se reporta a AFIP? | Proceso batch mensual (SICORE/SIRE) | Queda declarada en el propio comprobante fiscal al momento de emitirlo |
| ¿Requiere certificado para la contraparte? | Sí, certificado de retención (PDF) | No es obligatorio un certificado aparte (queda en el comprobante) |

**Pendiente de confirmar con el usuario**: cuando se mencionó que también aplica "en ventas/cobros", falta confirmar si se refiere al caso de **percepción** (lo natural, dado que retención por definición es del lado de los pagos) o si existe además un régimen puntual real de retención aplicada sobre un cobro (existe en casos específicos, pero es la excepción, no la regla). Esto define si hace falta también una ruta de "retención sobre cobro" además de la percepción estándar.

## Infraestructura reutilizable

El WSAA (autenticación con certificado ante AFIP) **ya está construido**: `ARCA/ArcaFunctions.php` tiene `arca_obtener_ta($cuit, $ambiente)`, que obtiene/renueva el Ticket de Acceso con lock para concurrencia. Ese mismo TA sirve para cualquier webservice de AFIP — para el Padrón alcanza con pedir el ticket para el servicio `ws_sr_padron_a13` (el más completo: trae categoría de IVA, Ganancias, Monotributo y actividades) en vez de `wsfe`. No hay que rehacer la autenticación.

## Política de caché del Padrón: al revés que en la integración de Mercado Libre

En el diseño de sincronización de stock con ML (ver `futura_integración_ML-MP.md`) el criterio fue "cachear y refrescar periódicamente, la consulta en vivo es la excepción" — porque ahí un dato desactualizado por unos minutos genera, en el peor caso, una venta duplicada reversible.

**Acá el riesgo es distinto y más grave**: si la categoría de un proveedor/cliente está vieja y por eso se **retiene/percibe de menos**, quien responde ante AFIP por la diferencia es **la empresa como agente**, con intereses y multa — no la contraparte. Si se retiene/percibe **de más**, se le genera un problema de caja o un cobro indebido a la contraparte. Por eso acá el criterio se invierte:

1. **En el momento de calcular la retención/percepción** (al confirmar el pago o el cobro), el sistema intenta la consulta **en vivo** al Padrón de AFIP para esa contraparte.
2. Si AFIP responde, se usa ese dato fresco y de paso se actualiza el caché local.
3. Si AFIP no responde en ese momento (caído, timeout), recién ahí se usa el último dato cacheado como respaldo — **mostrando un aviso explícito al operador** ("No se pudo verificar la categoría actual ante AFIP, se está usando el último dato conocido del [fecha]") para que decida si confía en eso o prefiere verificar antes de continuar.

No hay problema de performance con este enfoque: a diferencia de una venta de mostrador (muchas por minuto), pagar a un proveedor o cobrar una factura no ocurre con la frecuencia suficiente como para que una consulta en vivo por operación genere un cuello de botella real.

## Modelo de datos (borrador conceptual)

- **`config_agente_retencion_percepcion`**: las 4 banderas (retención IVA, retención Ganancias, percepción IVA, percepción Ganancias) por tenant, cada una con fecha de designación y número de resolución de AFIP de referencia. Es el gate de todo lo demás.
- **`padron_afip_cache`**: cuit, categoría IVA, categoría Ganancias, actividad principal, fecha de última consulta exitosa. Es el respaldo para cuando AFIP no responde en el momento, no la fuente primaria.
- **`retenciones_percepciones_regimenes`**: impuesto (IVA/GANANCIAS), tipo (RETENCION/PERCEPCION), categoría del sujeto, alícuota, monto mínimo no sujeto, vigente_desde/hasta. Se carga y actualiza a mano cuando AFIP cambia algo por resolución — no se hardcodea en código.
- **`retenciones_practicadas`**: fecha, proveedor_id, impuesto, base de cálculo, alícuota aplicada, importe retenido, pago asociado, número de certificado, estado (pendiente de reportar / reportado a SICORE).
- **`percepciones_practicadas`**: fecha, cliente_id, impuesto, base de cálculo, alícuota aplicada, importe percibido, comprobante fiscal asociado (ya queda declarado en ese mismo comprobante ante ARCA, así que este registro es sobre todo para informes propios).

## Cálculo: el mínimo no sujeto a retención suele ser acumulado mensual

Sobre todo en Ganancias (RG 830), el monto mínimo no sujeto a retención generalmente se acumula **por mes y por contraparte**, no se evalúa por operación aislada. Si ya se le pagó $50.000 a un proveedor esta semana y hoy se le paga $30.000 más, puede que el acumulado del mes ya haya superado el mínimo aunque el pago de hoy solo no lo supere. El cálculo tiene que sumar lo ya pagado/cobrado en el mes a esa contraparte antes de decidir si corresponde retener/percibir, no mirar cada operación de forma aislada.

## Alerta de condición fiscal desactualizada (agregado a pedido del usuario)

La `condicionfiscal` que ya se guarda en `alb_clientes` y `Proveedores` (Responsable Inscripto / Monotributo / Exento) — la misma que usa la validación de tipo de comprobante agregada el 2026-08-31 en `api_facturar.php` — hoy es **100% manual**, nunca se contrasta contra AFIP. La consulta al Padrón que se hace para retenciones/percepciones trae ese mismo dato de regalo: se puede usar para **alertar** (no cambiar solo) cuando lo guardado localmente no coincide con lo que dice AFIP en ese momento, para que un administrador lo revise y corrija a mano si corresponde.

## Flujo por caso

**Retención (al pagar a un proveedor, en `proveedores_pagar_facturas.php`):**
0. Chequeo de bandera: ¿la empresa es agente de retención de IVA y/o Ganancias? Si ninguna está activa, no se hace nada de lo siguiente.
1. Para cada impuesto con la bandera activa: consulta en vivo (o caché de respaldo con aviso) de la categoría del proveedor.
2. Cálculo con la tabla de alícuotas + mínimo acumulado del mes.
3. Se muestra el monto calculado al operador antes de confirmar el pago (con opción de ajuste manual justificado).
4. Se descuenta del pago, se genera el certificado de retención en PDF, se registra en `retenciones_practicadas`.
5. Reporte batch mensual a AFIP (SICORE/SIRE) con todo lo acumulado en `retenciones_practicadas` del período — proceso aparte, no atado a cada pago.

**Percepción (al facturar/cobrar a un cliente, en `newfactura.php` / `cobrar_facturas.php`):**
0. Chequeo de bandera: ¿la empresa es agente de percepción de IVA y/o Ganancias? Si ninguna está activa, no se hace nada de lo siguiente.
1. Para cada impuesto con la bandera activa: consulta en vivo (o caché de respaldo con aviso) de la categoría del cliente.
2. Cálculo con la tabla de alícuotas + mínimo acumulado del mes.
3. Se suma al total del comprobante.
4. Se declara como campo `Tributos` en el mismo llamado a ARCA que ya se hace para pedir el CAE — no es una llamada nueva a ARCA, son datos extra en la que ya existe.
5. Registro informativo en `percepciones_practicadas` para reportes propios.

## Orden de implementación sugerido

1. Pantalla de configuración con las 4 banderas de agente — el gate de todo, y lo más simple de construir.
2. Integración con el Padrón de AFIP (`ws_sr_padron_a13`), reutilizando `arca_obtener_ta()`, y ya sirve por sí sola para la alerta de condición fiscal desactualizada.
3. Tabla de regímenes/alícuotas + carga manual inicial de los valores vigentes.
4. Retención en pagos a proveedores (no toca facturación electrónica, más aislado).
5. Percepción en ventas/cobros (sí toca el llamado a ARCA existente, requiere más cuidado por el riesgo fiscal de tocar `api_facturar.php`).
6. Certificado de retención en PDF.
7. Reporte batch mensual (SICORE/SIRE).

## Pendiente de decidir cuando se arranque a construir

- Confirmar si el caso "ventas/cobros" es percepción estándar o si existe además un régimen real de retención sobre cobros (ver sección de distinción más arriba).
- Formato exacto de exportación para SICORE/SIRE (puede haber cambiado el mecanismo de AFIP al momento de implementar, conviene revisar la normativa vigente en ese momento).
- Definir si la alerta de condición fiscal desactualizada es solo visual (badge en la ficha del cliente/proveedor) o también genera una notificación proactiva.
- Definir umbral de "caché vencido" para el fallback cuando AFIP no responde (ej. no usar un dato de más de X días como respaldo silencioso, forzar confirmación manual si es muy viejo).
