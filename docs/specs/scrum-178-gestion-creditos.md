# Spec: Gestión de Créditos (SCRUM-178)

**Date**: 2026-08-04
**Requested by**: Luis (reportado por Dynamo Si)
**Status**: Draft — pendiente de aprobación de Luis antes de pasar a diseño del Arquitecto
**Project**: Proseguir Factoring

## Problem

No existe hoy ningún punto único donde el Coordinador Comercial gestione el resultado de una
solicitud una vez sale de validación (SARLAFT desfavorable) o del Comité de Créditos (aprobada,
rechazada o aplazada). Cada resultado queda disperso:

- **SARLAFT desfavorable**: `ListasRestrictivasSarlaftController::finalizar()` ya transiciona el
  crédito a `rechazado` y **envía el correo al cliente automáticamente**, sin que el Coordinador
  redacte ni revise el mensaje.
- **Decisión del Comité**: el módulo Actas de Comité (SCRUM-169/183) ya captura
  `ActaComiteSolicitud.estado_decision` (aprobado/rechazado/pendiente) por solicitud, pero por
  diseño explícito **no mueve** `CreditoOrdinario.estado` — decisión tomada a propósito el
  2026-08-02 dejando la integración "fuera de alcance" de ese ticket, anticipando que un ticket
  posterior la resolvería (ver `docs/specs/scrum-169-actas-comite-credito.md`, sección "Relación
  con el workflow existente"). El estado real hoy solo avanza por un botón manual y separado en
  la pantalla de Crédito Ordinario (`comite_evaluacion` → aprobar exige subir `acta_comite_firmada`
  a mano; no existe ningún estado que preserve un resultado "Pendiente").
- No existe ningún registro de "solicitud gestionada" ni "fecha de la gestión" en el sistema.

## Solution summary

Nuevo módulo **Gestión de Créditos**: una bandeja con 4 tarjetas + filtros/columnas (spec completa
en el ticket, sección 3) y 4 pantallas de gestión (una por resultado: Garantías, SARLAFT
desfavorable, Rechazada por Comité, Pendiente por Comité — sección 5), donde el Coordinador
Comercial redacta destino/asunto/mensaje (y selecciona preset de documentación cuando aplica) y
dispara **Registrar y enviar notificación**, que es el único punto que marca
`Solicitud gestionada = Sí`, registra `Fecha de la gestión` y ejecuta la transición de estado
correspondiente.

### Decisiones ya tomadas con Luis (2026-08-04)

- **SARLAFT desfavorable**: se **retira** el auto-envío del correo al cliente en
  `ListasRestrictivasSarlaftController::finalizar()`. El concepto desfavorable sigue transicionando
  el crédito a `rechazado` de inmediato (sin cambios ahí), pero el correo al cliente pasa a
  depender exclusivamente de que el Coordinador lo gestione desde esta nueva bandeja. El correo
  interno a Coordinadores (`SarlaftDesfavorableCoordinadorMail`, que hoy los avisa de un nuevo
  rechazo) **se mantiene** — es justamente lo que les señala que hay un ítem nuevo por gestionar.
- **Fuente de la decisión del Comité**: se conecta `ActaComiteSolicitud.estado_decision` para que
  **registrar el acta** sea lo que efectivamente mueva `CreditoOrdinario.estado` de cada solicitud
  incluida (aprobado/rechazado/pendiente). Esto completa la integración que SCRUM-169 dejó
  explícitamente pendiente. El botón manual aprobar/rechazar/devolver que hoy existe en
  `comite_evaluacion` (Crédito Ordinario) queda obsoleto para ese estado una vez el acta se
  registra primero — el Arquitecto define si se retira o se restringe a superadmin como fallback.

## Cambios de datos propuestos (a validar por el Arquitecto)

### Nuevas columnas en `credito_ordinarios`
| Columna | Tipo | Notas |
|---|---|---|
| `solicitud_gestionada` | boolean, default false | Igual criterio que `sarlaft_concepto`: se resetea si el resultado se revierte (ver "Casos borde"). |
| `fecha_gestion` | date/datetime nullable | Se setea solo tras envío exitoso del correo (VAL-07/VAL-08). |
| `resultado_origen` | enum nullable: `sarlaft`, `comite_aprobado`, `comite_rechazado`, `comite_pendiente` | Evita inferir el origen de un `rechazado` por join/heurística (SARLAFT vs Comité) para las 4 tarjetas y la columna Estado de la bandeja. |
| `gestion_detalle` | json nullable | Trazabilidad de lo enviado: destino, asunto, mensaje, preset_id (si aplica), requiere_documentos (si aplica), gestionado_por_id. Mismo patrón que `historial_estados`. |

### Nuevos valores de `credito_ordinarios.estado`
| Estado | Se llega desde | Tarjeta en la bandeja |
|---|---|---|
| `aprobada_garantias` | Acta registrada con `estado_decision = aprobado` | Aprobados para gestión de garantías |
| `pendiente_comite` | Acta registrada con `estado_decision = pendiente` | Pendientes - Comité de Créditos |
| `rechazado` (ya existe) | SARLAFT desfavorable (inmediato, sin cambios) **o** Acta registrada con `estado_decision = rechazado` | Listas Restrictivas y SARLAFT desfavorable / Rechazados - Comité de Créditos (distinguidas por `resultado_origen`) |

Transición al gestionar (tabla 5.5 del ticket):
- `aprobada_garantias` → **`formalizacion_garantias`** (estado ya existente, ya habilita la carga
  real en Mis Créditos — no se crea un estado nuevo para esto).
- `rechazado` → se mantiene `rechazado` (solo cambia `solicitud_gestionada`/`fecha_gestion`).
- `pendiente_comite` → se mantiene `pendiente_comite` (solo cambia `solicitud_gestionada`/
  `fecha_gestion`; si "requiere documentación" = Sí, se crea una `DocumentRequest` reutilizando
  `DocumentRequestController::store()` con el `preset_id` seleccionado, mismo mecanismo que
  SCRUM-146).

## Reuso identificado
- **Selección de preset**: mismo componente/flujo que ya usa Solicitud de Documentos
  (`DocumentPreset` + `DocumentRequestController::store(preset_id, cliente_id)`), para el preset
  obligatorio de Garantías y el condicional de Pendiente por Comité (VAL-04/VAL-05).
- **Envío de correo**: patrón `Mailable` + `Mail::to()->send()` ya existente
  (`app/Mail/*`), pero con contenido dinámico (asunto/mensaje editados por el Coordinador) en vez
  de las vistas Blade fijas actuales — nueva `Mailable` genérica en vez de una por estado.
- **displaySteps / mapa de estados** del componente `credito-ordinario.component.ts` como
  referencia de patrón para pintar Estado en la bandeja nueva.
- **Resolución de URL de documentos** (`CreditoOrdinario::resolveStorageUrl`) para la descarga de
  la síntesis SARLAFT en la pantalla de gestión (sección 5.2, ya existe el campo
  `sintesis_oficial_cumplimiento`).

## Casos borde a resolver en diseño
- Si una solicitud vuelve a pasar por Comité (p. ej. `pendiente_comite` se re-evalúa en una acta
  posterior y ahora sale `aprobado`), ¿se resetea `solicitud_gestionada` a No? El ticket no lo
  cubre explícitamente; el criterio por defecto propuesto es sí (nueva decisión = nueva gestión
  pendiente), a confirmar con el Arquitecto.
- Qué pasa con el slot manual `documentos['acta_comite_firmada']` una vez el acta registrada
  dispara la transición sola — ¿se retira el requisito de subirlo, o queda como respaldo
  documental sin bloquear el avance? SCRUM-169 lo dejó "sin cambios en paralelo"; este ticket
  probablemente lo vuelve redundante para el avance de estado (aunque puede seguir sirviendo de
  archivo adjunto).

## Fuera de alcance (igual al ticket original)
Validación de listas (Auditor Interno), decisión del Comité en sí (se toma en Actas Comité),
configuración de presets (Configuración de Requisitos), carga documental del cliente (Mis
créditos).
