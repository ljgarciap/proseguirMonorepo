# SCRUM-133 — Solicitud de cuentas: APIs de IA (Anthropic, Google, Mistral) y SMTP

**Estado:** Listo para enviar a quien gestione la creación de cuentas (facturación/procurement).
**Titular de facturación:** Proseguir.

## Contexto

Proseguir Factoring usa un pipeline de OCR (`ProcessUploadJob`) para extraer datos de los
documentos que suben los clientes (facturas, extractos, formularios). Hoy corre con dos
proveedores de IA ya integrados en el código (Gemini primario, Mistral como fallback
automático), pero **sin una cuenta oficial de facturación a nombre de Proseguir** — las
API keys actuales se gestionan desde `/configuraciones` (tabla en base de datos, solo rol
`superadmin`, nunca en `.env`), pero la titularidad de la cuenta que las emitió no está
resuelta.

Esta solicitud consolida las 3 cuentas de IA (Anthropic, Google, Mistral) y el acceso SMTP
necesario para que las notificaciones por correo (registro de solicitudes de crédito,
resultado SARLAFT, etc.) salgan desde una casilla real del dominio, no desde el modo `log`
por defecto.

---

## 1. Google — Gemini API (YA EN USO, falta cuenta oficial)

| Campo | Detalle |
|---|---|
| Servicio | Generative Language API (Gemini) |
| Modelo usado en producción | `gemini-2.5-flash` |
| Endpoint | `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent` |
| Autenticación | API key (query param `key`) |
| Uso actual | Motor **primario** de OCR — procesa el 100% de los documentos subidos salvo que falle |
| Prioridad | Alta — ya está en producción, corriendo con una key sin cuenta de facturación formal |

**Se solicita:** cuenta de Google Cloud a nombre de Proseguir, con facturación activa y la
Generative Language API habilitada, y una API key de producción para reemplazar la actual.

## 2. Mistral AI (YA EN USO, falta cuenta oficial)

| Campo | Detalle |
|---|---|
| Servicio | Mistral OCR + Chat Completions (la Plateforme) |
| Modelos usados | `mistral-ocr-latest` (extracción OCR), `mistral-small-latest` (estructuración del texto extraído a JSON) |
| Endpoint base | `https://api.mistral.ai` |
| Autenticación | Bearer token |
| Uso actual | **Fallback automático** — solo se activa si Gemini falla en un documento |
| Prioridad | Alta — mismo motivo que Gemini, es parte del mismo pipeline en producción |

**Se solicita:** cuenta en Mistral AI a nombre de Proseguir, y una API key de producción.

## 3. Anthropic — Claude API (NUEVO, reservado para automatización futura)

| Campo | Detalle |
|---|---|
| Estado actual | **Sin integración en el código todavía** — no hay ningún feature que use Claude hoy |
| Motivo de la solicitud | Reservar la cuenta y la API key con anticipación para una automatización futura (línea "automatización PSL"), aún sin especificar ni diseñar |
| Prioridad | Media — no bloquea nada en producción, se puede crear cuando haya disponibilidad |

**Se solicita:** cuenta en Anthropic Console a nombre de Proseguir y una API key. No hay uso
inmediato — la key queda guardada en `/configuraciones` hasta que el feature correspondiente
tenga su propio ticket y spec.

## 4. SMTP — correo dedicado a notificaciones transaccionales

| Campo | Detalle |
|---|---|
| Casilla propuesta | `notificaciones@proseguirliquidez.com` |
| Uso | Envío real de correos ya implementados: notificación de registro de solicitud de crédito (`SolicitudCreditoMail`), resultado desfavorable de SARLAFT a cliente y coordinador (SCRUM-128) |
| Prioridad | Alta |

**Estado técnico actual:** el sistema ya intenta enviar estos correos de verdad (no hay ningún
modo de simulación en el código), pero el driver de correo (`MAIL_MAILER`) en el `.env` de
referencia del repo está en `log` por defecto — no hay forma de confirmar desde el código si
el servidor de producción ya tiene un SMTP real configurado o si estos correos se están
quedando solo en el log del servidor.

**Se solicita:** host SMTP, puerto, usuario y contraseña de la casilla `notificaciones@proseguirliquidez.com` (o el correo real que se decida usar).

**Nota técnica para el equipo de desarrollo (no bloquea la solicitud):** los campos
`MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` ya viven
en la tabla `configuraciones` (editables desde `/configuraciones`, solo `superadmin`) — pero
`MAIL_MAILER` (el driver `smtp` vs `log`) hoy es una variable de entorno pura del servidor,
no gestionada desde ese panel. Una vez cargadas las credenciales SMTP, alguien con acceso al
servidor debe confirmar/cambiar `MAIL_MAILER=smtp` en el `.env` de producción — de lo
contrario los correos seguirán sin salir aunque las credenciales estén bien cargadas.
Candidato a ticket aparte: mover `MAIL_MAILER` a la tabla `configuraciones` para no depender
de acceso SSH cada vez que se active o desactive el envío real.

---

## Dónde se cargan las credenciales una vez creadas

Todas las API keys (Anthropic, Google, Mistral) y las credenciales SMTP se cargan desde
`/configuraciones` — acceso restringido a rol `superadmin`, y los valores marcados como
secretos no se exponen en texto plano vía API una vez guardados (solo se puede sobrescribir).

## Resumen

| Cuenta | Titular | Estado | Prioridad |
|---|---|---|---|
| Google (Gemini) | Proseguir | Ya en uso, falta cuenta oficial | Alta |
| Mistral AI | Proseguir | Ya en uso, falta cuenta oficial | Alta |
| Anthropic (Claude) | Proseguir | Nuevo, reservado | Media |
| SMTP `notificaciones@proseguirliquidez.com` | Proseguir | Pendiente de confirmar en producción | Alta |
