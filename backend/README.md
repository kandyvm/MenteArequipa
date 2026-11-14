# MENTE Arequipa: API (backend)

API REST de MENTE Arequipa, hecha con **Laravel 12** (PHP 8.2 o superior), **Laravel Sanctum** en modo SPA y **PostgreSQL**.
La interfaz está en la carpeta `../frontend` (Vue 3). La instalación completa y el despliegue se explican en el [README principal](../README.md).

## Puesta en marcha

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Antes de migrar, crea las bases `mente_arequipa` y `mente_arequipa_test` en PostgreSQL y completa `DB_USERNAME` y `DB_PASSWORD` en `.env`.

## Estructura

| Carpeta | Contenido |
|---|---|
| `app/Models` | Modelos Eloquent: `Usuario`, `Joven`, `Consentimiento`, `Solicitud`, `Derivacion`, `Seguimiento`, `Centro`, `Recurso`, `Auditoria` |
| `app/Http/Controllers` | Controladores de la API (autenticación, solicitudes, derivaciones, consulta pública, centros, usuarios, recursos, dashboard y auditoría) |
| `app/Http/Requests` | Validación en el servidor (Form Requests) |
| `app/Http/Resources` | Formato de las respuestas JSON |
| `app/Http/Middleware` | `EnsureUsuarioActivo`: bloquea cuentas desactivadas |
| `app/Policies` | Autorización de solicitudes, derivaciones y centros |
| `app/Observers` | Registran en la auditoría los cambios de solicitudes y derivaciones |
| `database/migrations` | Esquema de la base, incluido el trigger que impide modificar o borrar registros de la auditoría (no cubre `TRUNCATE`) |
| `database/seeders` | Centros y usuarios de prueba |
| `routes/api.php` | Todas las rutas de la API |
| `tests/Feature/SeguridadTest.php` | Pruebas de seguridad |

## Autenticación

El login usa la sesión de Laravel con Sanctum en modo SPA: el frontend pide primero `GET /sanctum/csrf-cookie`
y luego envía las peticiones con cookies y el encabezado `X-XSRF-TOKEN`. No se emiten tokens personales.

## Rutas principales

| Acceso | Rutas |
|---|---|
| Públicas | `POST /api/registro`, `POST /api/login` (5 por minuto), `GET /api/consulta/{codigo}` (10 por minuto) |
| Con sesión | `POST /api/logout`, `GET /api/yo`, `GET /api/dashboard/resumen`, `solicitudes`, `derivaciones`, `centros`, `usuarios`, `recursos` y `GET /api/auditoria` |

Detalle completo en `routes/api.php`. La ruta `GET /up` sirve para comprobar que la aplicación está viva.

## Flujo de una solicitud

`REGISTRADA` → `EN_REVISION` (el orientador la revisa) → `DERIVADA` (se asigna un centro) → `ACEPTADA` → `ATENDIDA`.
También puede quedar `CERRADA` o `CANCELADA`. Si el centro rechaza la derivación, la solicitud vuelve a `EN_REVISION`.
Cada cambio de estado de una derivación se guarda en `seguimientos`, y los cambios de estado de la solicitud, en la auditoría.

La auditoría también registra los inicios de sesión (correctos y fallidos de cuentas existentes), los cierres de sesión, el registro de jóvenes y los cambios de usuarios, centros y recursos (crear, editar, desactivar, reactivar, publicar, ocultar y eliminar).

## Pruebas

```powershell
php artisan test
```

Las pruebas usan PostgreSQL (base `mente_arequipa_test`) porque el esquema usa `INET` y triggers.
Incluyen contraseñas con hash, inyección SQL, XSS, acceso por rol, límite de intentos, cuentas desactivadas,
consentimiento y reglas de cierre de solicitudes.

## Docker

El `Dockerfile` de esta carpeta es el que usa Render. Instala las dependencias de PHP y, al arrancar, ejecuta
`config:clear`, `migrate`, `db:seed` y `php artisan serve`. Si una migración o el seeder fallan, el arranque continúa y el error queda en los logs.
