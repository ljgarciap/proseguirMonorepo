// SCRUM-346 (seguimiento, a pedido explícito de Luis — "lo ideal es que
// sea desde la base de datos"): este archivo dejó de ser un mapa
// hardcodeado. Ya se rompió 2 veces (SCRUM-331, SCRUM-346) — un rename de
// rol hecho desde Roles y Permisos persiste bien en la BD, pero cualquier
// mapa hardcodeado en el frontend queda desactualizado hasta el próximo
// deploy que lo toque a mano.
//
// Ahora el nombre real de cada rol viaja en la respuesta de
// login()/me() (AuthController::etiquetasDeRoles(), 'role_labels': mapa
// completo slug → nombre de TODO el catálogo, no solo los roles del
// usuario) y AuthService lo cachea (memoria + localStorage, mismo patrón
// que 'all_roles'/'permissions'). Este archivo solo guarda esa caché y la
// expone vía getRoleLabel(slug) — la misma firma de función que ya usaban
// los 6 lugares consumidores (login, menú de usuario, Mi Perfil, Gestión
// de Usuarios, Roadmap, Crédito Ordinario), así que ninguno tuvo que
// cambiar. setRoleLabelsCache() la llama AuthService, no llamar desde
// otro lado.
let cache: Record<string, string> = {};

export function setRoleLabelsCache(labels: Record<string, string> | null | undefined): void {
  cache = labels ?? {};
}

/** Label legible de un slug de rol, leído de la caché sembrada por
 * AuthService desde la BD. Si el slug no está en la caché (rol nuevo
 * creado en la misma sesión antes del próximo login, o caché todavía sin
 * cargar) cae al transform genérico anterior en vez de mostrar el slug
 * crudo. */
export function getRoleLabel(slug: string | null | undefined): string {
  if (!slug) {
    return '';
  }
  return cache[slug] ?? slug.split('_').join(' ').replace(/\b\w/g, c => c.toUpperCase());
}
