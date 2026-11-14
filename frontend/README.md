# MENTE Arequipa: frontend (Vue 3)

SPA en Vue 3 + Vite + Tailwind CSS + Vue Router + Pinia + Axios. Consume la API REST de Laravel (rutas en backend/routes/api.php).

## Ejecutar
```bash
cd frontend
npm install
npm run dev        # http://localhost:5173
```
Con el backend en `http://localhost:8000` (`php artisan serve`). Vite reenvía `/api` y `/sanctum` al backend para que el navegador vea un solo origen (requisito de Sanctum en modo SPA).

En `backend/.env` agregar: `SANCTUM_STATEFUL_DOMAINS=localhost:5173`

## Pantallas (todas responsive, mobile-first)
| Ruta | Pantalla | Rol |
|---|---|---|
| `/login` | Iniciar sesión | Público |
| `/registro` | Crear cuenta (18 a 29 años, consentimiento) | Público |
| `/consulta/:codigo?` | Consulta pública con línea de tiempo de 5 etapas | Público |
| `/solicitudes/nueva` | Formulario de 3 pasos + código de seguimiento | JOVEN |
| `/panel` | Dashboard (indicadores, listado, acciones según rol) | Todos |
| `/derivar/:id` | Derivación asistida | ORIENTADOR |
| `/centros` | Directorio de centros; el ADMIN los gestiona y cada CENTRO edita el suyo | ADMIN, CENTRO |
| `/usuarios` | Cuentas de ORIENTADOR, CENTRO y ADMIN | ADMIN |
| `/recursos` | Recursos de orientación; el ADMIN los gestiona y los demás ven los publicados | Todos |
| `/auditoria` | Registro de auditoría de solo lectura | ADMIN |

Banner de la Línea 113 en todas las pantallas; aviso con botón de llamada al elegir riesgo CRISIS.

## Contrato con el backend
- `POST /api/login` `{correo, password}` → `{id, rol}`
- `POST /api/registro` `{correo, password, password_confirmation, fecha_nacimiento, distrito, telefono, acepta_consentimiento}` → 201 `{id, rol}` (que las contraseñas coincidan lo comprueba el frontend)
- `GET /api/dashboard/resumen` (solo ORIENTADOR) → `{data:{solicitudes_nuevas, derivaciones_en_curso, casos_atendidos, derivaciones_113}}`
- `GET /api/solicitudes?estado=&nivel_riesgo=&page=` → `{data:[{id, codigo, motivo, descripcion, nivel_riesgo, canal, estado, derivada_113, creada_en, derivaciones:[…]}], links, meta}` (15 por página)
- `GET /api/centros?distrito=` → `{data:[{id, nombre, tipo, distrito, direccion, latitud, longitud, servicios, activo}]}`
- `POST /api/solicitudes/{id}/derivaciones` `{centro_id, prioridad (1, 3 o 5), observaciones}`
- `PATCH /api/derivaciones/{id}` `{estado: ACEPTADA | RECHAZADA | ATENDIDA}`
- `GET /api/consulta/{codigo}` → `{data:{codigo, estado, centro, actualizado_en}}`

## Accesibilidad (WCAG 2.1 AA)
Enlace "Saltar al contenido", foco visible de 2 px, etiquetas en todos los campos, errores con `aria-describedby`/`role="alert"`, estado siempre con texto (no solo color), objetivos táctiles de 44 px, `aria-current` en la línea de tiempo, textos de color con contraste ≥ 4.5:1.
