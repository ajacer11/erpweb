# Liquidación de Sueldos / RRHH (diseño, sin implementar)

Estado: **diseño cerrado, sin código escrito**. Este documento es la referencia para cuando se decida arrancar el desarrollo.

Se planea ofrecer como módulo adicional (no incluido por defecto en ninguna licencia) — ver el mecanismo de habilitación en `futura_arquitectura_modulos_adicionales.md`.

## Alcance pedido

Liquidación de sueldos **completa**: convenios laborales, empleados fuera de convenio, cargas sociales y aportes, generación de recibo, contabilidad asociada, anticipos, presentismo, ausentismo, licencias, aguinaldo (SAC), vacaciones e indemnizaciones por despido.

**Explícitamente fuera de esta vuelta**: la presentación mensual ante AFIP (F.931/SICOSS) queda como fase aparte a diseñar después (mismo criterio que se usó con SICORE en el documento de retenciones/percepciones).

## Estado actual del sistema

No existe ningún módulo de RRHH/liquidación de sueldos hoy — es una integración completamente nueva. Es, en tamaño, comparable a un producto aparte (la mayoría de los ERPs argentinos lo venden como módulo separado, ej. Tango RRHH, BEJERMAN), así que este diseño está pensado en piezas que se puedan construir e integrar de forma incremental.

## 1. Legajo del empleado

Datos personales, CUIL, domicilio, fecha de nacimiento, fecha de ingreso y de egreso (si aplica) con motivo, tipo de contrato (indeterminado, plazo fijo, eventual, pasantía, temporada), convenio + categoría asociados (o marcado "fuera de convenio"), obra social elegida, sindicato/afiliación, CBU para acreditación de haberes, cargas de familia (relevante para asignaciones familiares, un sub-sistema de ANSES en sí mismo que puede quedar para una vuelta posterior).

## 2. Convenios laborales (multi-convenio)

- `convenios_laborales` es una tabla normal, **no un dato único de la empresa**: un tenant puede tener varios convenios activos a la vez (ej. administrativos bajo Convenio de Comercio y operarios bajo UOM en la misma empresa). Cada legajo referencia cuál convenio y categoría le corresponde.
- Cada convenio tiene sus categorías con un básico **versionado por fecha de vigencia** — las paritarias actualizan los básicos cada 2-4 meses en Argentina, así que la tabla de escalas necesita historial, mismo patrón que la tabla de alícuotas versionada del diseño de retenciones (`retenciones_percepciones_regimenes`).
- Cada convenio trae también sus propios adicionales (antigüedad %, título, zona, presentismo — las reglas de cuándo se pierde varían por convenio).

## 3. Empleados fuera de convenio

Sueldo bruto pactado individualmente, no deriva de una escala. Aun así, los aportes y contribuciones legales aplican igual que a cualquier empleado en relación de dependencia. El legajo necesita poder marcar "aplica / no aplica" por concepto (ej. un fuera de convenio a veces no tiene presentismo como concepto separado porque ya está incluido en el sueldo pactado).

## 4. Novedades del período

- **Anticipos**: adelantos de sueldo entregados durante el mes. Salen de tesorería como un pago normal, marcado "a cuenta de sueldo", y se descuentan automáticamente del neto en la liquidación del período correspondiente.
- **Presentismo**: porcentaje del básico que se pierde total o parcialmente según las reglas de ausencias del convenio (umbral configurable, ej. "más de 1 inasistencia injustificada = pierde presentismo completo").
- **Ausentismo**: registro de días/horas de ausencia con tipo (justificada con certificado médico, injustificada, enfermedad de familiar). Afecta el cálculo de presentismo y puede generar descuento proporcional del básico si es injustificada.
- **Licencias**: además de vacaciones (ver punto 6), licencias especiales con días fijos por ley/convenio (nacimiento, matrimonio, examen, fallecimiento de familiar, etc.).
- **Horas extra**: con recargo (50% días hábiles, 100% feriados/fines de semana según ley), cantidad de horas cargadas por período.

## 5. Cargas sociales: aportes y contribuciones

- **Aportes del empleado** (se descuentan del bruto): jubilación (11%), Ley 19.032/PAMI (3%), obra social (3%, +1.5% con familiar a cargo).
- **Contribuciones patronales** (a cargo de la empresa, no se descuentan del recibo pero generan gasto y pasivo): jubilación (~10.77% o 12.71% según tamaño de empresa/actividad, con reducciones para PyMEs), obra social (6%), Fondo Nacional de Empleo (0.89%), ART (variable según tabla de riesgo de la aseguradora contratada), asignaciones familiares.
- **Sindicato**: cuota sindical, descontada si el empleado está afiliado.
- Todos estos porcentajes cambian por decreto/resolución ocasionalmente — misma tabla versionada por vigencia que el resto (convenios, aportes).

## 6. Vacaciones (banco de días)

- Motor de banco de días por legajo: días que corresponden según antigüedad (Ley de Contrato de Trabajo art. 150 — 14 días hasta 5 años, 21 de 5 a 10, 28 de 10 a 20, 35 más de 20 años), acumulados por año calendario, gozados y saldo disponible.
- Proporcional si el empleado no trabajó el año completo.
- Al gozarlas se liquida el "plus vacacional" con la fórmula legal (art. 155 LCT: mejor sueldo mensual del último año / 25 × días de vacaciones gozados), como un concepto dentro del recibo del período en que se gozan.

## 7. Aguinaldo (SAC)

- Se paga en dos cuotas (30/6 y 18/12, o último día hábil correspondiente).
- Cálculo: 50% de la mejor remuneración mensual, normal y habitual, percibida en el semestre respectivo. Proporcional si no trabajó el semestre completo.
- Para calcularlo bien, `liquidaciones` tiene que ser un **histórico real** por legajo (no solo el último mes) — hay que poder mirar los 6 meses anteriores para encontrar la mejor remuneración.
- Contablemente se trata igual que un sueldo normal (mismo tipo de asiento).

## 8. Indemnizaciones por despido / Liquidación Final

Es la pieza de mayor riesgo legal (litigiosidad laboral alta en Argentina), así que se diseña como una **pantalla separada** de "Liquidación Final" — no un botón dentro de la liquidación mensual regular — con cálculo asistido y revisión antes de emitir:

- Indemnización por antigüedad (art. 245 LCT): 1 mejor sueldo mensual normal y habitual por año de antigüedad o fracción mayor a 3 meses, con **tope parametrizable** (el tope depende del convenio/escala salarial del sector y cambia periódicamente).
- Preaviso, o su indemnización sustitutiva si no se otorgó.
- Integración del mes de despido.
- SAC proporcional sobre todo lo anterior.
- Vacaciones no gozadas proporcionales.

## 9. Generación de recibo

PDF con el formato legal obligatorio (Res. MTESS 2015/86 y modificatorias): razón social y CUIT del empleador, datos completos del empleado, período liquidado, conceptos remunerativos y no remunerativos claramente separados con su base de cálculo cada uno, total bruto, cada descuento detallado, neto, espacio de firma/recepción. Mismo patrón de generación de PDF que el certificado de retención del otro diseño.

## 10. Contabilidad asociada

Un asiento automático más — sería el proceso 22 (ya existen 21 configurables en `asientos_auto_cfg.php`), con el mismo mecanismo de cuentas contables por defecto configurables:

- **Debe**: Sueldos y Jornales (bruto remunerativo) + Cargas Sociales Patronales (contribuciones).
- **Haber**: Retenciones/Aportes a Depositar (lo retenido al empleado, pasivo hasta pagarlo a ANSES/obra social/sindicato) + Contribuciones a Pagar (pasivo hasta pagarlas) + Sueldos a Pagar (neto, pasivo hasta que se acredita al empleado).

## 11. F.931 / SICOSS — fuera de alcance por ahora

La transmisión mensual de la declaración jurada de aportes y contribuciones a AFIP queda como **fase aparte**, a diseñar después (mismo criterio que SICORE en el documento de retenciones/percepciones). El modelo de datos de este diseño (liquidaciones con aportes y contribuciones ya calculados y detallados por concepto) queda preparado para que, cuando se aborde esa fase, no haga falta rediseñar la base — solo agregar el proceso de exportación/transmisión.

## Modelo de datos (borrador conceptual)

- `legajos`: datos del empleado, convenio_id (nullable si es fuera de convenio), categoria_id, obra social, CBU, fecha ingreso/egreso.
- `convenios_laborales` / `categorias_convenio`: básico vigente versionado por fecha.
- `aportes_contribuciones_regimenes`: porcentajes versionados por fecha de vigencia.
- `novedades_periodo`: legajo, período, tipo (anticipo/ausencia/licencia/hora extra), detalle.
- `vacaciones_banco`: legajo, año, días correspondientes, días gozados, saldo.
- `liquidaciones`: legajo, período, detalle de conceptos aplicados con montos, total remunerativo, total no remunerativo, aportes, contribuciones, neto — histórico completo (no se sobrescribe mes a mes, hace falta para SAC).
- `liquidaciones_finales`: legajo, fecha de egreso, motivo, detalle del cálculo de indemnización — tabla separada de `liquidaciones` por el riesgo legal distinto que implica.

## Orden de implementación sugerido

1. Legajo + convenios/categorías + tabla de aportes/contribuciones versionada — base de todo lo demás.
2. Liquidación mensual regular (básico + adicionales + aportes/contribuciones + recibo + asiento contable), sin novedades todavía.
3. Novedades del período: anticipos, presentismo, ausentismo, horas extra.
4. Vacaciones (banco de días + liquidación del plus vacacional).
5. Aguinaldo (SAC) — depende de tener histórico de liquidaciones ya funcionando (punto 2).
6. Liquidación Final / indemnizaciones por despido — la de mayor riesgo legal, se deja para el final una vez que el resto está probado.
7. (Fase aparte, no en esta vuelta) F.931/SICOSS.

## Pendiente de decidir cuando se arranque a construir

- Si se van a manejar asignaciones familiares (ANSES) como parte de este módulo o quedan fuera.
- Si hay integración con reloj/asistencia para horas trabajadas y horas extra, o toda la carga de novedades es manual.
- Topes exactos vigentes al momento de implementar (indemnización por antigüedad, bases imponibles de aportes) — hay que revisar la normativa actualizada en ese momento, no la de hoy.
- Si el Libro de Sueldos Digital se diseña junto con F.931/SICOSS en la fase aparte, o es una tercera fase distinta.
