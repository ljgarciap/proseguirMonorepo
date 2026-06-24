# Proseguir Factoring — Proyecto

Plataforma web de gestión de liquidez y factoring financiero.
Las reglas globales del equipo viven en `../../CLAUDE.md` (raíz del workspace Softclass).
Este archivo documenta solo lo específico de este proyecto.

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel (PHP 8.4) + Laravel Passport (OAuth2) |
| Frontend | Angular 17 |
| Base de datos | MySQL 8.0 |
| Automatización | n8n |
| Servidor web | Nginx (proxy inverso, puerto 80) |
| Infraestructura | Docker Compose (monorepo) |
| CI/CD | GitHub Actions → push a `master` despliega a producción |

---

## Repositorio

```
Proseguir/
├── backend/        ← Laravel (PHP 8.4)
├── frontend/       ← Angular 17
├── nginx/          ← configuración del proxy
├── workflow/       ← backup del flujo n8n
├── docker-compose.yml
└── memory/         ← estado persistente del proyecto
```

---

## Contenedores Docker

| Contenedor | Rol |
|---|---|
| `factoring_db` | MySQL 8.0 |
| `factoring_backend` | Laravel (PHP-FPM) |
| `factoring_backend_web` | Nginx proxy |
| `factoring_queue` | Laravel queue worker |
| `factoring_frontend` | Angular (servido por Nginx) |
| `factoring_n8n` | ~~Motor de automatización n8n~~ (F9: pendiente de eliminar) |

---

## Red de comunicación

| Origen | Destino | URL |
|---|---|---|
| Frontend | API | `http://auto.proseguirliquidez.com/api` |
| Frontend | n8n | `http://auto.proseguirliquidez.com/n8n` |
| Laravel | n8n | `http://factoring_n8n:5678` (red Docker interna) |
| n8n | Laravel | `http://factoring_backend_web/api` (red Docker interna) |

---

## Git y CI/CD

- Rama principal: `master` — push directo → CI/CD automático a producción
- **Nunca** hacer `git pull` ni migraciones manualmente en el servidor
- PRs a `master` → solo corren tests (no despliegan)
- Commits en español, formato: `tipo(scope): descripción`

---

## Reglas críticas de este proyecto

- Las llaves de Passport (`oauth-private.key`, `oauth-public.key`) deben tener permisos `600` **siempre** — el `chmod 600` va al final del deploy, después del `chmod -R 777 storage`
- Al reconstruir contenedores, siempre reiniciar `backend_web` después para evitar 502 por caché DNS de Nginx
- El enlace simbólico de storage debe ser **relativo**: `ln -s ../storage/app/public public/storage` (no absoluto)
- El script de extracción de PDF es `extract_pdf.cjs` (CommonJS, no `.js`) para evitar errores ESM

---

## Memoria del proyecto

`memory/` contiene el estado persistente:

```
memory/
├── MEMORY.md           ← leer primero al iniciar sesión
└── project-state.md    ← arquitectura, lecciones, módulos clave
```

---

## Invocación del Context Keeper

```
Act as the Context Keeper.
Active project: Proseguir Factoring
Memory index: /Users/lgarcia/Documents/GitHub/Softclass/Factoring/Proseguir/memory/MEMORY.md
Briefings output: /Users/lgarcia/Documents/GitHub/Softclass/Factoring/Proseguir/memory/briefings/
Backend repo: /Users/lgarcia/Documents/GitHub/Softclass/Factoring/Proseguir/backend
Frontend repo: /Users/lgarcia/Documents/GitHub/Softclass/Factoring/Proseguir/frontend
Project CLAUDE.md: /Users/lgarcia/Documents/GitHub/Softclass/Factoring/Proseguir/CLAUDE.md

Read all memory files. Check recent git log on both repos.
Produce briefings for: Arquitecto, Backend Dev, Frontend Dev, DevOps, PM, QA.
```

---

## Entorno local de desarrollo

```bash
# Levantar todo
cd Factoring/Proseguir
docker compose up -d

# Logs backend
docker compose logs -f factoring_backend

# Artisan
docker exec -it factoring_backend php artisan <comando>

# Angular dev (fuera de Docker)
cd frontend && npm start
```
