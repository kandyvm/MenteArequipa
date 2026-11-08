# MENTE Arequipa

Plataforma web de orientación y trazabilidad de derivaciones para jóvenes de 18 a 29 años que buscan
atención en salud mental en Arequipa. Proyecto académico de Programación Web (Universidad Continental, NRC 40537).

> **La plataforma no es un servicio de emergencia ni emite diagnósticos.** En una crisis: **Línea 113, opción 5**.

> **Solo datos de prueba.** El despliegue de prueba está en Render (aplicación en Docker y base de datos PostgreSQL), que aloja datos fuera del Perú.
> Está prohibido cargar información real hasta resolver el flujo transfronterizo exigido por la Ley N.° 29733.

## Tecnologías

Laravel 12 (PHP 8.2 o superior) bajo el patrón MVC, con vistas Blade, PostgreSQL 16 y Tailwind CSS con Vite. La autenticación usa sesiones nativas de Laravel y las contraseñas se guardan con hash bcrypt.

## Requisitos

PHP 8.2+, Composer, Node.js 20, PostgreSQL 16 y Git.

## Instalación

```powershell
cd backend
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Crea las bases en PostgreSQL (`mente_arequipa` y `mente_arequipa_test`), escribe tu `DB_USERNAME` y `DB_PASSWORD`
en `.env` y ejecuta:

```powershell
php artisan migrate --seed
npm run build
php artisan serve
```

### Variables de entorno importantes

| Variable | Para qué sirve |
|---|---|
| `DB_*` | Conexión a PostgreSQL |
| `APP_KEY` | Clave de la aplicación (se genera con `php artisan key:generate`) |
| `APP_DEBUG` | Debe ser `false` en producción |
| `APP_TIMEZONE` | Zona horaria (por defecto `America/Lima`) |

El archivo `.env` nunca se sube al repositorio.

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

## Rutas principales

`/login` · `/registro` · `/consulta` (público, con límite de intentos) · `/solicitudes` · `/derivaciones` · `/centros` · `/auditoria`

## Seguridad

Contraseñas con hash bcrypt, 4 roles con autorización por policies, protección CSRF, escape de salidas con Blade, consultas con Eloquent y parámetros (prevención de inyección SQL), límite de intentos en login y consulta pública, y auditoría inmutable con triggers en PostgreSQL.

## Ramas

`main` (estable, solo Pull Request desde `develop`), `develop` (integración), `feature/*` y `fix/*`.
Commits con Conventional Commits.

## Licencia

Código bajo licencia MIT (ver `LICENSE`). Documentación bajo Creative Commons BY-NC-SA 4.0.
