import axios from 'axios'

/** Cliente HTTP. Sanctum (modo SPA): cookie de sesión + token CSRF en cada petición que modifica datos. */
export const api = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

/** Errores de validación 422 → { campo: 'mensaje' } */
export function erroresDe(e) {
  const errs = e.response?.data?.errors ?? {}
  return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]))
}

/** Mensaje genérico y seguro para mostrar al usuario según el código HTTP. */
export function mensajeDe(e) {
  const s = e.response?.status
  if (s === 429) return 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'
  if (s === 403) return 'No tienes permiso para esta acción.'
  if (s === 404) return 'No encontramos lo que buscas.'
  if (s === 422) return e.response?.data?.message ?? 'Revisa los datos ingresados.'
  if (!e.response) return 'No hay conexión con el servidor. Revisa tu internet.'
  return 'Ocurrió un problema. Inténtalo de nuevo.'
}
