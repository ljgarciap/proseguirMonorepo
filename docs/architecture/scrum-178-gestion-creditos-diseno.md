# Diseño técnico: Gestión de Créditos (SCRUM-178)

**Date**: 2026-08-04
**Status**: Aprobado por Luis para pasar a implementación
**Basado en**: `docs/specs/scrum-178-gestion-creditos.md`

## 1. Diagrama de estados (solo lo que cambia)

```
                     [Gerencia aprueba presentación]
                                  │
                                  ▼
                          comite_evaluacion
                                  │
              ActaComiteController::registrar()
         (por cada ActaComiteSolicitud incluida, según estado_decision)
        ┌─────────────────┬───────────────────┬─────────────────┐
        │ aprobado         │ rechazado          │ pendiente        │
        ▼                  ▼                    ▼
 aprobada_garantias     rechazado          pendiente_comite
 (resultado_origen=     (resultado_origen=  (resultado_origen=
  comite_aprobado)       comite_rechazado)   comite_pendiente)
        │                  │                    │
        │            [ya es terminal,           │
        │             igual que hoy]             │
        │                                        │
   Gestión de Créditos: Registrar y enviar notificación
        │                                        │
        ▼                                        ▼
 formalizacion_garantias                 pendiente_comite (mismo estado,
 (ya existente, sin cambios               solo cambia solicitud_gestionada/
  desde acá en adelante)                  fecha_gestion; si requiere_docs=Sí
                                           dispara DocumentRequest)

Camino paralelo, sin tocar comite_evaluacion:

 sarlaft_control_interno
        │ Oficial de Cumplimiento finaliza con concepto=desfavorable
        ▼
   rechazado (resultado_origen=sarlaft)   ← YA transicionaba así hoy, sin cambios
        │
   Gestión de Créditos: Registrar y enviar notificación
        │ (antes: acá se enviaba el correo automático — SE RETIRA)
        ▼
   rechazado (mismo estado, solo cambia solicitud_gestionada/fecha_gestion)
```

`comite_evaluacion` deja de tener un botón manual de aprobar/rechazar en
`CreditoOrdinarioController::transition()` — la única salida de ese estado pasa a ser
`ActaComiteController::registrar()`.

## 2. Resolución de los casos borde de la spec

**Re-decisión de una solicitud ya gestionada** (ej. `pendiente_comite` vuelve a evaluarse en una
acta posterior y ahora sale `aprobado`): al recalcular `resultado_origen`/estado en
`registrar()`, si el crédito ya tenía `solicitud_gestionada = true`, se resetea a `false` y
`fecha_gestion` a `null` — una nueva decisión implica una nueva gestión pendiente, con su propio
correo. `gestion_detalle` anterior no se borra, se conserva como historial dentro de un array
(no se sobrescribe un único objeto).

**`documentos['acta_comite_firmada']`**: dejó de ser requisito para transicionar (la transición
ahora la dispara `registrar()` sola). Para no perder la continuidad documental, `registrar()`
copia automáticamente la ruta del PDF generado del acta (`ActaComiteController::descargar()` ya
genera ese PDF vía dompdf) al campo `documentos['acta_comite_firmada']` de cada
`CreditoOrdinario` incluido, usando `documentos_raw` para no re-hornear URLs (mismo criterio
SCRUM-148). El slot de subida manual sigue existiendo en la UI de Crédito Ordinario por si se
necesita reemplazar el PDF por una versión firmada externamente, pero no bloquea nada.

## 3. Migraciones

`database/migrations/2026_08_04_XXXXXX_add_gestion_fields_to_credito_ordinarios_table.php`
```php
Schema::table('credito_ordinarios', function (Blueprint $table) {
    $table->boolean('solicitud_gestionada')->default(false)->after('sarlaft_diligenciado_at');
    $table->timestamp('fecha_gestion')->nullable()->after('solicitud_gestionada');
    $table->string('resultado_origen')->nullable()->after('fecha_gestion');
    // sarlaft | comite_aprobado | comite_rechazado | comite_pendiente — validado en app, no en BD
    $table->json('gestion_detalle')->nullable()->after('resultado_origen');
});
```

No se crea tabla nueva: es 1:1 por crédito y el volumen no justifica normalizar (mismo criterio
que `historial_estados`).

## 4. Backend

### 4.1 `ActaComiteController::registrar()` — cambios
Después de `$acta->estado = 'aprobada'`, por cada `ActaComiteSolicitud` con `credito_ordinario_id`
no nulo:
1. Mapear `estado_decision` → estado/`resultado_origen` (tabla arriba).
2. Si `solicitud_gestionada` ya era `true`, resetear (`false`/`null`) — caso borde de re-decisión.
3. Copiar el PDF del acta a `documentos_raw['acta_comite_firmada']`.
4. Guardar `historial_estados` (mismo formato que `CreditoOrdinario::transition`).

Nota: las solicitudes agregadas manualmente al acta (`origen != 'sistema'`, créditos que no
existen en el sistema) no tienen `credito_ordinario_id` — se ignoran en este paso, igual que ya
las ignora el resto del módulo.

### 4.2 `ListasRestrictivasSarlaftController`
- `finalizar()`: al marcar `desfavorable`, setear `resultado_origen = 'sarlaft'` además de
  `estado = 'rechazado'`.
- `notificarDesfavorable()`: **eliminar** el `Mail::to($credito->cliente->email)->send(...)`.
  Mantener el envío a `SarlaftDesfavorableCoordinadorMail` (aviso interno).
- Retirar `App\Mail\SarlaftDesfavorableClienteMail` y la vista
  `emails.sarlaft_desfavorable_cliente` (ya no se usan) o dejarlos sin referenciar si Luis prefiere
  no borrar código — a decidir por Backend Dev al implementar, sin impacto funcional.

### 4.3 `CreditoOrdinarioController::transition()`
- Retirar `'comite_evaluacion' => ['comite_credito']` de `$rolesAutorizados` (ya no hay acción
  humana ahí) y el bloque `case 'comite_evaluacion':` dentro del switch de `aprobar`/`subir_archivo`
  — devolver 422 explicativo si algo intenta llamarlo: *"Esta transición ahora se ejecuta desde
  Actas de Comité al registrar el acta."*

### 4.4 Nuevo `GestionCreditoController`
Ruta: `Route::prefix('gestion-creditos')->middleware('auth:api')->group(...)`, roles
`coordinador_comercial,superadmin` (`checkrole`).

| Método | Endpoint | Uso |
|---|---|---|
| GET | `/gestion-creditos` | Bandeja: filtros (búsqueda, tipo_credito, tipo_persona, estado, gestionada), orden por `fecha_validacion` (calculada: `sarlaft_diligenciado_at` o `fecha` del acta/comité según `resultado_origen`), luego `fecha_gestion` NULLS FIRST, luego `numero_solicitud`. |
| GET | `/gestion-creditos/tarjetas` | Conteos de las 4 tarjetas (`solicitud_gestionada = false` agrupado por estado). |
| GET | `/gestion-creditos/{credito}` | Detalle solo lectura: cliente + crédito + info de origen (SARLAFT u observaciones/fecha del Comité vía `ActaComiteSolicitud`). |
| POST | `/gestion-creditos/{credito}/notificar` | Body: `destino`, `asunto`, `mensaje`, `preset_id` (solo aprobada_garantias, obligatorio), `requiere_documentos` + `preset_id` (solo pendiente_comite, condicional). Ejecuta VAL-01..05, envía `GestionCreditoNotificacionMail`, y solo si el envío no lanza excepción: transiciona estado (si aplica), marca `solicitud_gestionada=true`, `fecha_gestion=now()`, guarda `gestion_detalle`, y si corresponde crea la `DocumentRequest` (reusando lógica de `DocumentRequestController::store`). |

`Route::get('/gestion-creditos/{credito}/sintesis-sarlaft')` no hace falta: el PDF ya se sirve
resuelto vía `documentos.sintesis_oficial_cumplimiento` del propio `show()`.

### 4.5 Nueva `Mailable`: `App\Mail\GestionCreditoNotificacionMail`
Constructor recibe `$credito`, `$asunto`, `$mensajeHtml`. Una sola vista genérica
(`emails.gestion_credito_notificacion`) que envuelve el mensaje libre del Coordinador con el
membrete estándar (mismo layout base que las `Mailable` existentes, si hay uno compartido —
verificar en `resources/views/emails/` al implementar).

## 5. Frontend (Angular)

Nuevo módulo bajo `frontend/src/app/components/gestion-creditos/`:
- `gestion-creditos-bandeja/` — tarjetas + filtros + tabla (patrón ya usado en
  `listas-restrictivas-sarlaft` y `actas-comite` para bandejas con conteos).
- `gestion-creditos-detalle/` — un solo componente parametrizado por `resultado_origen` /
  `estado` del crédito (Garantías / SARLAFT desfavorable / Rechazada Comité / Pendiente Comité
  comparten el mismo layout de "info readonly arriba + formulario de notificación abajo"; solo
  cambian los campos obligatorios del formulario) en vez de 4 componentes casi idénticos —
  reduce duplicación y es donde vive VAL-01..05.
- Ruta `/gestion-creditos` en el menú, visible solo para `coordinador_comercial`/`superadmin`
  (mismo patrón de guard que Actas de Comité).

## 6. Orden de implementación sugerido (PM)

1. Migración + cambios de modelo (`CreditoOrdinario` fillable/casts).
2. `ActaComiteController::registrar()` — integración con la máquina de estados.
3. `ListasRestrictivasSarlaftController` — retirar auto-envío, setear `resultado_origen`.
4. `CreditoOrdinarioController::transition()` — retirar rama `comite_evaluacion`.
5. `GestionCreditoController` + rutas + `Mailable` genérica.
6. Frontend: bandeja.
7. Frontend: pantalla de detalle/gestión parametrizada.
8. Tests (backend Feature tests nuevos + regresión de `ActaComiteTest`,
   `CreditoOrdinarioTest`, `ListasRestrictivasSarlaftTest` si existe).
9. Pre-QA (Proseguir ya lo tiene adoptado) antes de pasar a QA formal.
