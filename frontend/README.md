# MENTE Arequipa: frontend (Vue 3)

SPA en Vue 3 + Vite + Tailwind CSS + Vue Router + Pinia + Axios. Consume la API REST de Laravel (Anexo H del informe).

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

Banner de la Línea 113 en todas las pantallas; aviso con botón de llamada al elegir riesgo CRISIS.

## Contrato asumido con el backend (confirmar con quien hace la API)
- `POST /api/login` `{correo, contrasena}` → `{data:{id, rol}}`
- `POST /api/registro` `{correo, contrasena, contrasena_confirmation, fecha_nacimiento, distrito, telefono, acepta_consentimiento}`
- `GET /api/dashboard/resumen` → `{data:{solicitudes_nuevas, derivaciones_en_curso, casos_atendidos, derivadas_113}}`
- `GET /api/solicitudes` → `{data:[{id, codigo, motivo, nivel_riesgo, estado, derivacion:{id, estado}|null}]}`
- `GET /api/centros?distrito=` → `{data:[{id, nombre, tipo, distrito}]}`
- `POST /api/solicitudes/{id}/derivaciones` `{centro_id, prioridad, observaciones}`
- `PATCH /api/derivaciones/{id}` `{estado}`; `GET /api/consulta/{codigo}` según el Anexo H.

## Accesibilidad (WCAG 2.1 AA)
Enlace "Saltar al contenido", foco visible de 2 px, etiquetas en todos los campos, errores con `aria-describedby`/`role="alert"`, estado siempre con texto (no solo color), objetivos táctiles de 44 px, `aria-current` en la línea de tiempo, textos de color con contraste ≥ 4.5:1.
