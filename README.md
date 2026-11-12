# MENTE Arequipa

Plataforma web de orientación y trazabilidad de derivaciones para jóvenes de 18 a 29 años que buscan
atención en salud mental en Arequipa. Proyecto académico de Programación Web (Universidad Continental, NRC 40537).

> **La plataforma no es un servicio de emergencia ni emite diagnósticos.** En una crisis: **Línea 113, opción 5**.

> **Solo datos de prueba.** El despliegue de prueba está en Render (backend en Docker, sitio estático y base de datos PostgreSQL), que aloja datos fuera del Perú.
> Está prohibido cargar información real hasta resolver el flujo transfronterizo exigido por la Ley N.° 29733.

## Arquitectura

El sistema tiene dos partes que se despliegan por separado:

```
Navegador ──► Sitio estático (frontend/, Vue 3)
                 │  Rewrites de Render: /api/* y /sanctum/*
                 ▼
              API REST (backend/, Laravel 12) ──► PostgreSQL
```

- **`backend/`**: API REST en Laravel 12 con patrón MVC (modelos Eloquent, controladores, Form Requests, Resources y Policies).
- **`frontend/`**: aplicación de una sola página (SPA) en Vue 3 que consume la API.
- La sesión usa **Laravel Sanctum en modo SPA** (cookie de sesión y token CSRF). No se usan tokens personales.
- El navegador ve un solo origen: en producción lo logran las reglas Rewrite de Render, y en desarrollo el proxy de Vite.

## Tecnologías

| Capa | Tecnologías |
|---|---|
| Backend | Laravel 12, PHP 8.2 o superior, Laravel Sanctum 4, PostgreSQL |
| Frontend | Vue 3, Pinia, Vue Router, Axios, Tailwind CSS 3, Vite 5 |
| Pruebas y CI | PHPUnit (`php artisan test`) y GitHub Actions (backend y frontend) |
| Despliegue | Render (Docker para el backend, sitio estático para el frontend) |

## Requisitos

PHP 8.2+, Composer, Node.js 20, PostgreSQL y Git. El CI usa PostgreSQL 16 y la base de Render es PostgreSQL 18.

## Instalación local

### 1. Backend

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Crea las bases en PostgreSQL (`mente_arequipa` y `mente_arequipa_test`), escribe tu `DB_USERNAME` y `DB_PASSWORD`
en `.env` y ejecuta:

```powershell
php artisan migrate --seed
php artisan serve
```

La API queda en `http://localhost:8000`.

### 2. Frontend

En otra terminal:

```powershell
cd frontend
npm install
npm run dev
```

La aplicación queda en `http://localhost:5173`. El proxy de Vite reenvía `/api` y `/sanctum` a `http://localhost:8000`.

### Variables de entorno importantes (backend)

| Variable | Para qué sirve |
|---|---|
| `DB_*` | Conexión a PostgreSQL |
| `APP_KEY` | Clave de la aplicación (se genera con `php artisan key:generate`) |
| `APP_DEBUG` | `true` solo en desarrollo; debe ser `false` en producción |
| `APP_TIMEZONE` | Zona horaria (por defecto `America/Lima`) |
| `SANCTUM_STATEFUL_DOMAINS` | Dominio del frontend que puede usar la sesión (en producción, el del sitio estático) |
| `CORS_ALLOWED_ORIGINS` | Orígenes permitidos, separados por coma |
| `SESSION_DRIVER`, `SESSION_SECURE_COOKIE` | Almacén de sesión y cookie segura (`true` en producción) |
| `CLAVE_ADMIN_PRUEBA` | Contraseña de la cuenta ADMIN de prueba (opcional) |
| `CLAVE_CUENTAS_PRUEBA` | Contraseña de las cuentas JOVEN, ORIENTADOR y CENTRO de prueba (opcional) |

El archivo `.env` nunca se sube al repositorio.

## Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| JOVEN | joven@prueba.pe | De demostración: `Prueba2026!` |
| ORIENTADOR | orientador@prueba.pe | De demostración: `Prueba2026!` |
| CENTRO (Centro de Salud Mental San Agustín) | centro.sanagustin@prueba.pe | De demostración: `Prueba2026!` |
| CENTRO (Hospital Honorio Delgado) | centro.honorio@prueba.pe | De demostración: `Prueba2026!` |
| CENTRO (Centro Comunitario de Prueba) | centro.comunitario@prueba.pe | De demostración: `Prueba2026!` |
| ADMIN | admin@prueba.pe | Propia; se entrega por privado al docente |

Cada cuenta de centro solo ve y acepta las derivaciones dirigidas a su propio centro. Para probar el flujo completo,
deriva la solicitud al centro cuya cuenta vas a usar.

Son cuentas con datos de prueba. El seeder se ejecuta en cada arranque del contenedor y vuelve a establecer estas
contraseñas: la del ADMIN sale de `CLAVE_ADMIN_PRUEBA` y la de las demás, de `CLAVE_CUENTAS_PRUEBA`.
Si esas variables no existen (por ejemplo, en una instalación local), todas usan `Prueba2026!`.

## Pantallas del frontend

| Ruta | Quién la usa |
|---|---|
| `/consulta/:codigo?` | Público: consulta el estado de un caso con su código |
| `/login`, `/registro` | Visitantes: iniciar sesión y crear cuenta de joven |
| `/solicitudes/nueva` | JOVEN: crea una solicitud en 3 pasos |
| `/panel` | Todos los roles: lista de solicitudes según el rol (el ORIENTADOR ve además un resumen) |
| `/derivar/:id` | ORIENTADOR: deriva una solicitud a un centro |
| `/centros` | ADMIN y CENTRO: gestión de centros de atención |
| `/usuarios` | ADMIN: crea, edita, desactiva y reactiva cuentas de ORIENTADOR, CENTRO y ADMIN |
| `/recursos` | ADMIN: gestiona los recursos de orientación. Los demás roles ven los publicados |
| `/auditoria` | ADMIN: registro de auditoría de solo lectura |

Las cuentas de jóvenes las crea cada persona desde `/registro`; el ADMIN no las crea desde la pantalla de usuarios.

## API

La API vive bajo `/api`. Resumen de rutas (detalle en `backend/routes/api.php`):

- **Públicas:** `POST /api/registro`, `POST /api/login` (5 intentos por minuto) y `GET /api/consulta/{codigo}` (10 por minuto).
- **Con sesión:** `POST /api/logout`, `GET /api/yo`, `GET /api/dashboard/resumen`, y CRUD de `solicitudes`, `derivaciones`, `centros`, `usuarios` y `recursos`, además de `GET /api/auditoria`.
- La autorización la deciden las Policies (solicitudes, derivaciones y centros) y las comprobaciones de rol en los controladores.

Estados de una solicitud: `REGISTRADA`, `EN_REVISION`, `DERIVADA`, `ACEPTADA`, `ATENDIDA`, `CERRADA` y `CANCELADA`.

## Pruebas

```powershell
cd backend
php artisan test
php artisan test --filter=SeguridadTest
```

Las pruebas usan la base `mente_arequipa_test` (PostgreSQL), porque el esquema usa `INET` y triggers.
GitHub Actions las ejecuta en cada push y Pull Request; también compila el frontend (`npm ci` y `npm run build`).

## Despliegue en Render

| Servicio | Configuración |
|---|---|
| Backend (Docker) | Raíz `backend`, Dockerfile `backend/Dockerfile`. Despliegue manual. |
| Frontend (sitio estático) | Repositorio `kandyvm/MenteArequipa`, rama `main`, raíz `frontend`, build `npm install && npm run build`, publica `dist`. Se vuelve a desplegar cuando cambia la rama, salvo que se apague el Auto-Deploy. |
| Base de datos | PostgreSQL de Render (plan gratuito, con fecha de vencimiento). |

El sitio estático necesita tres reglas Rewrite: `/sanctum/*` y `/api/*` hacia el backend, y `/*` hacia `/index.html`
(para que funcionen las rutas de Vue). Al arrancar, el contenedor del backend ejecuta las migraciones y el seeder;
si una de las dos falla, el arranque continúa y el error queda en los logs.

## Seguridad

- Contraseñas con hash bcrypt.
- Cuatro roles con autorización por Policies y comprobaciones de rol, y bloqueo de cuentas desactivadas.
- Sesión con Sanctum en modo SPA: cookie de sesión y protección CSRF.
- Prevención de inyección SQL con Eloquent y consultas parametrizadas.
- Prevención de XSS: el servidor limpia los textos con `strip_tags` y el frontend no inserta HTML sin escapar.
- Límite de intentos en login, registro y consulta pública.
- Teléfono cifrado en la base y consentimiento informado guardado con su versión.
- Auditoría inmutable: triggers de PostgreSQL impiden modificar o borrar sus registros desde la aplicación.

## Ramas

`main` es la rama estable. El trabajo se hace en ramas `feature/*` y `fix/*` y se integra con Pull Request.
La mayoría de los commits usa el formato Conventional Commits (`feat`, `fix`, `docs`, `chore`).

## Licencia

Código bajo licencia MIT (ver `LICENSE`). Documentación bajo Creative Commons BY-NC-SA 4.0.
