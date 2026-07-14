# Diseño técnico: SCRUM-120 Fase 2 — Fidelidad completa a prototipos (fórmulas, bandeja, descarga)

**Date**: 2026-07-14
**Status**: Approved — Luis confirmó replicar las fórmulas del Excel tal cual, incluidas 2 particularidades que parecían errores
**Reemplaza el alcance simplificado de la Fase 1** (campos `valor_total`/`detalle` genéricos) por el modelo real del prototipo.

## Por qué este documento existe
La Fase 1 (mergeada a `dev`/`test`) implementó el flujo de estados y roles correctamente, pero simplificó los campos del formulario a un total + texto libre por sección, sin haber revisado los adjuntos visuales de Jira. Al revisar los 5 prototipos + el Excel de referencia (`INFORME TÉCNICO - ENTRE VERDE M+D.xlsx`, código interno `CR-RO-09A v5`), la brecha real es: faltan los campos itemizados, las fórmulas exactas, las acciones de bandeja (Iniciar/Continuar/Abrir/Ver Solicitud/Descargar) y la descarga PDF/Excel. Este documento fija el contrato exacto para cerrar esa brecha.

## Fuente de verdad: mapeo exacto de celdas del Excel

Todas las fórmulas siguientes fueron extraídas directamente del archivo adjunto en SCRUM-120 (hoja "ENTREVERDE"). Se replican **tal cual**, incluidas 2 particularidades confirmadas con Luis como intencionales (no se "corrigen"):
- Costos (E15) **no** suma Honorarios (E20) ni Financieros (E23) pese al título de la sección — así está en el documento de control interno vigente.
- Cobertura de Garantía (F78) usa `E9` (Apartamentos) como "Valor Total en Ventas", no `E7` (total real) — así está en el Excel.

### Sección Ingeniero — Ventas Totales Proyecto
| Campo | Fórmula |
|---|---|
| Casas | input |
| Apartamentos | input |
| Parqueaderos | input |
| Conexión gas/arras desist. | input |
| Local comercial | input |
| Cuartos útiles | input |
| Otros | input |
| **Total Ventas** | `= Casas + Apartamentos + Parqueaderos + ConexionGas + LocalComercial + CuartosUtiles + Otros` |
| % sobre ventas (por cada línea) | `= línea / Total Ventas` |

### Sección Ingeniero — Costos (incluido costo financiero, ver nota arriba)
| Campo | Fórmula |
|---|---|
| Lote | input |
| Directos | input |
| Directos Urbanismo | input |
| Indirectos | input |
| Honorarios | input (se captura, **no se suma** al total) |
| Incremento en costos | input |
| Financieros | input (se captura, **no se suma** al total) |
| **Total Costos** | `= Lote + Directos + DirectosUrbanismo + Indirectos + IncrementoEnCostos` |
| % costos | `= Total Costos / Total Ventas` |

Mostrar advertencia visible junto al total: *"Honorarios y Financieros no se incluyen en el total de Costos (regla del documento de control CR-RO-09A)."*

### Sección Ingeniero — Invertido
| Campo | Fórmula |
|---|---|
| Lote | input |
| Costos Directos | input |
| Costos Indirectos | input |
| **Total Invertido** | `= Lote + CostosDirectos + CostosIndirectos` |
| % invertido | `= Total Invertido / Total Ventas` |
| Recursos propios | input |
| Cuotas Iniciales Ya Pagadas | input |
| **Total Fuentes** | `= RecursosPropios + CuotasInicialesYaPagadas` |

### Sección Ingeniero — Observaciones
Texto libre, obligatorio para registrar (ya implementado en Fase 1, sin cambios).

### Sección Coordinador Comercial — Crédito Solicitado
| Campo | Fórmula |
|---|---|
| Crédito Solicitado | input |
| % sobre ventas | `= CréditoSolicitado / TotalVentas` |
| Aptos. Vendidos | input |
| % sobre ventas | `= AptosVendidos / TotalVentas` |
| Cuotas Iniciales Ya Pagadas | `= Invertido.CuotasInicialesYaPagadas` (referencia directa a la sección del Ingeniero) |
| % para cuotas iniciales pendientes | input (en el Excel de ejemplo es 30%, tratar como campo editable, no constante fija) |
| Cuotas Iniciales Pendientes | `= AptosVendidos * %CuotasInicialesPendientes - CuotasInicialesYaPagadas` |
| **Saldo por Recaudar Contraentrega (vendidos)** | `= AptosVendidos - CuotasInicialesYaPagadas - CuotasInicialesPendientes` |

### Sección Coordinador Comercial — Saldo por Recaudar Contraentrega (por vender)
| Campo | Fórmula |
|---|---|
| Aptos x Vender | `= TotalVentas - AptosVendidos` |
| % sobre ventas | `= AptosXVender / TotalVentas` |
| % para cuotas iniciales (por vender) | input (ejemplo Excel: 10%) |
| Cuotas Iniciales | `= AptosXVender * %CuotasIniciales` |
| Cuotas Iniciales Pendientes | input (vacío en el ejemplo — dejar como input editable, sin fórmula fija visible en el Excel) |
| **Saldo por Recaudar Contraentrega (por vender)** | `= AptosXVender - CuotasIniciales - CuotasInicialesPendientes` |
| **Total Pendiente por Recaudar** | `= CuotasInicialesPendientes(vendidos) + SaldoContraentrega(vendidos) + AptosXVender` — replicar tal cual la fórmula real del Excel (`E40+E41+E42`), aunque a simple vista uno esperaría `E41+E45`; es la fórmula real del documento. |

### Análisis de Financiación
| Campo | Fórmula |
|---|---|
| Costo de la Obra | `= Total Costos (sección Ingeniero)` |
| (-) Valor Invertido | `= Total Invertido (sección Ingeniero)` |
| (-) Crédito | `= Crédito Solicitado` |
| Saldo x Financiar (bruto) | `= CostoObra - ValorInvertido - Crédito` |
| (-) Cuotas Iniciales x Recaudar | `= CuotasInicialesPendientes (vendidos)` |
| **Saldo x Financiar** | `= SaldoXFinanciarBruto - CuotasInicialesXRecaudar` |
| (-) Cuotas iniciales Pendientes | `= CuotasIniciales (por vender)` |
| **Saldo No Financiado** | `= SaldoXFinanciar - CuotasInicialesPendientes(por vender)` |

### Coberturas
| Escenario | Fórmula | Umbral sugerido (convenciones de color del prototipo) |
|---|---|---|
| A) Peor escenario (no vende más) | `= (Crédito + SaldoXFinanciar) / SaldoContraentrega(vendidos)` | rojo si > 0.7, verde si < 0.7 (ver prototipo 05, tabla de reglas — aplicar en el orden mostrado) |
| B) Mejor escenario (vende todo) | `= (Crédito + SaldoNoFinanciado) / (SaldoContraentrega(vendidos) + SaldoContraentrega(por vender))` | rojo si > 0.6, verde si < 0.6 |
| C) Cobertura de Garantía | `= Crédito / Apartamentos` (ver nota arriba: usa Apartamentos, no el total de ventas) | rojo si > 0.6, verde si < 0.6 |

No implementar el conditional-formatting exacto celda-por-celda del prototipo 05 (son reglas de Excel muy específicas y no completamente legibles en la captura) — usar semáforo simple rojo/ámbar/verde por escenario con los 3 umbrales de "Valores Máximos" (A: 100%, B: 70%, C: 60%) como referencia visual, documentado como aproximación.

### Encabezado (informativo, todas las pantallas)
Proyecto, Ubicación (ciudad), Dirección, Solicitante, Tipo de crédito, Estado documental — **campo nuevo requerido**: `proyecto` no existe hoy en ningún modelo. Se agrega a `solicitudes_credito` (nullable, solo se captura/exige cuando `tipo_credito = CONSTRUCTOR`). Ciudad y Dirección se toman de `Cliente.ciudad`/`Cliente.direccion` (ya existen).

## Modelo de datos (reemplaza el de Fase 1)

**Migración nueva** (no se modifica la tabla ya desplegada, se agregan las columnas granulares faltantes — la Fase 1 ya tiene `informes_tecnicos` con columnas JSON `ventas_totales_proyecto`, `costos`, `invertido`, `credito_solicitado`, `saldos_por_recaudar_contraentrega`, `analisis_financiacion`, `coberturas`; se **mantienen esas columnas JSON** como el contenedor de cada sección — no se explotan en columnas SQL individuales, ya que no hay necesidad real de consultarlas por SQL fuera de esta pantalla, y evita una migración de 30+ columnas. Lo que cambia es **qué guarda cada JSON** (la forma/shape), y se agrega el cálculo real en backend antes de guardar.

Nuevo campo en `solicitudes_credito`: `proyecto` (string, nullable).

## Componentes a modificar/crear

### Backend
- Migración: `proyecto` nullable en `solicitudes_credito`.
- `InformeTecnicoCalculoService` (nuevo): recibe los inputs crudos de cada sección y devuelve la sección completa con todos los totales/porcentajes calculados, replicando exactamente las fórmulas de la tabla de arriba. Se invoca desde `InformeTecnicoController::guardarBorrador()` y `registrar()` antes de persistir — el frontend manda solo los inputs, el backend calcula (nunca confiar en cálculo de frontend, ya establecido en la spec original).
- `InformeTecnicoController::index()`: agregar `proyecto`, `ciudad`, `direccion` a la respuesta (via `solicitudCredito.proyecto` y `cliente.ciudad`/`cliente.direccion`).
- Acciones de bandeja: el backend ya expone todo lo necesario (`estado`, si `informe` tiene datos o no) — la lógica de "Iniciar vs Continuar" es puramente de frontend (si `informe.observaciones_ingeniero` y campos de ventas están vacíos → Iniciar, si no → Continuar).
- Endpoint nuevo: `GET /api/informes-tecnicos/{creditoId}/descargar?formato=pdf|excel` — genera el documento consolidado (encabezado + sección Ingeniero + sección Coordinador + fórmulas calculadas + observaciones + trazabilidad de quién/cuándo). Si el informe no está `registrado` (finalizado), incluir marca "BORRADOR" visible. PDF vía `dompdf` (ya instalado, primera vez en uso). Excel vía patrón `Export` ya usado en el proyecto (`MandatoExport`, etc.).
- Endpoint nuevo: `GET /api/informes-tecnicos/{creditoId}/solicitud` (o reusar el endpoint existente de `SolicitudCreditoController::show` si existe) para el botón "Ver Solicitud" de la bandeja — revisar si ya hay un endpoint de detalle de solicitud reusable antes de crear uno nuevo.

### Frontend
- Reescribir `informe-tecnico-detalle.component.ts/html` con las grillas itemizadas reales (tablas Campo/Valor/%Ventas por sección, igual a los prototipos 03/04), inputs numéricos por línea, totales y porcentajes calculados **mostrados desde la respuesta del backend** (no recalculados en el cliente, para evitar divergencia).
- Sección Coordinador en modo lectura de la sección Ingeniero: tabla idéntica pero disabled.
- Semáforo de colores en Coberturas según los umbrales de la tabla de arriba.
- Botones "Descargar PDF" / "Descargar Excel" (habilitados según reglas del prototipo: disponible si hay datos del rol correspondiente, aunque sea borrador).
- Bandeja: agregar columnas Ciudad, Dirección; filtros de búsqueda (No. crédito, Ubicación, Estado); botones diferenciados Iniciar/Continuar/Abrir según si el informe ya tiene datos; botón "Ver Solicitud"; botón "Descargar informe" (habilitado apenas exista algo registrado).
- Encabezado del formulario: agregar campo "Proyecto" al formulario de creación de `SolicitudCredito` cuando el tipo es Constructor (para que exista el dato que la bandeja necesita mostrar).

## Riesgos
| Riesgo | Mitigación |
|---|---|
| Las fórmulas replicadas fielmente incluyen 2 particularidades no intuitivas (Costos sin Honorarios/Financieros, Cobertura de Garantía con Apartamentos) | Ya confirmado con Luis explícitamente — documentar con tooltips/notas visibles en el formulario para que no parezca un bug a futuros usuarios o desarrolladores. |
| `InformeTecnicoCalculoService` mal probado podría dar cifras financieras incorrectas | Tests unitarios con los valores reales del Excel de referencia (ENTRE VERDE M+D) como caso de prueba — los valores del Excel ya están en el archivo descargado, se pueden usar como fixture exacto. |
| Alcance grande para una sola iteración | Priorizar: (1) modelo de datos + cálculo backend + tests con fixture real, (2) formulario itemizado frontend, (3) bandeja completa, (4) descarga PDF/Excel — en ese orden, cada uno desplegable independientemente si el tiempo aprieta. |

## Fixture de prueba (valores reales del Excel adjunto, proyecto "Entre Verde M+D")
Para los tests del `InformeTecnicoCalculoService`, usar estos valores reales extraídos del Excel (permite verificar contra los totales ya calculados en el archivo original):
- Apartamentos: 32,841,282,386 (única línea de ventas con dato en el ejemplo)
- Costos: Lote 5,315,952,140 / Directos 12,690,962,100 / Directos Urbanismo 865,000,000 / Indirectos 8,771,866,121 / Honorarios 2,495,448,000 / Financieros 1,596,600,000
- Invertido: Lote 5,315,952,140 / Costos Directos 1,410,320,211 / Costos Indirectos 1,660,000,000 / Recursos propios 2,416,500,000 / Cuotas Iniciales Ya Pagadas 3,561,000,000
- Crédito Solicitado: 8,000,000,000 | Aptos Vendidos: 32,841,282,386 (mismo valor que ventas en este ejemplo — probablemente todo vendido)
