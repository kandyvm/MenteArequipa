# MENTE Arequipa

Plataforma web de orientación y trazabilidad de derivaciones para jóvenes de 18 a 29 años que buscan
atención en salud mental en Arequipa. Proyecto académico de Programación Web (Universidad Continental, NRC 40537).

> **La plataforma no es un servicio de emergencia ni emite diagnósticos.** En una crisis: **Línea 113, opción 5**.

> **Solo datos de prueba.** El despliegue de prueba está en Render (frontend estático, API en Docker y base de datos PostgreSQL), que aloja datos fuera del Perú.
> Está prohibido cargar información real hasta resolver el flujo transfronterizo exigido por la Ley N.° 29733.

## Tecnologías

Laravel 12 (PHP 8.2 o superior) con Sanctum, PostgreSQL 16 y Vue 3 con Vite y Tailwind (carpeta `frontend`).

## Requisitos

PHP 8.2+, Composer, Node.js 20, PostgreSQL 16 y Git.

## Instalación del backend

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

### Variables de entorno importantes

| Variable | Para qué sirve |
|---|---|
| `DB_*` | Conexión a PostgreSQL |
| `SANCTUM_STATEFUL_DOMAINS` | Dominio del frontend que recibe las cookies de sesión |
| `CORS_ALLOWED_ORIGINS` | Origen permitido del frontend (con credenciales) |
| `SESSION_DOMAIN`, `SESSION_SAME_SITE` | Ajustes de cookies cuando frontend y API están en subdominios |
| `APP_TIMEZONE` | Zona horaria (por defecto `America/Lima`) |

## Usuarios de prueba (contraseña `Prueba2026!`)

| Rol | Correo |
|---|---|
| JOVEN | joven@prueba.pe |
| ORIENTADOR | orientador@prueba.pe |
| CENTRO | centro@prueba.pe |
| ADMIN | admin@prueba.pe |

## Pruebas

```powershell
cd backend
php artisan test
php artisan test --filter=SeguridadTest
```

Las pruebas usan la base `mente_arequipa_test` (PostgreSQL), porque el esquema usa `INET` y triggers.

## Rutas principales de la API

`POST /api/login` · `POST /api/registro` · `GET /api/consulta/{codigo}` (público) · `/api/solicitudes` ·
`/api/derivaciones` · `/api/centros` · `/api/usuarios` · `/api/recursos` · `GET /api/auditoria` ·
`GET /api/dashboard/resumen`

## Ramas

`main` (estable, solo Pull Request desde `develop`), `develop` (integración), `feature/*` y `hotfix/*`.
Commits con Conventional Commits.

## Licencia

Código bajo licencia MIT (ver `LICENSE`). Documentación bajo Creative Commons BY-NC-SA 4.0.
