// SCRUM-331 (rebote 2026-09-07): el rename de "Coordinador Comercial" a
// "Director de Crédito" (605edcb) solo tocó los lugares que ya armaban su
// propio label a mano (stepper de Crédito Ordinario, Informe Técnico,
// roadmap, correos, PDF). Quedaron afuera los lugares que derivaban el
// label del slug on-the-fly con `slug.split('_').join(' ') | titlecase`
// (selector de perfil en login, menú de usuario, "Mi Perfil", tabla de
// Gestión de Usuarios) — ese transform siempre iba a mostrar "Coordinador
// Comercial" porque el slug interno (coordinador_comercial) no cambia a
// propósito (es el identificador técnico usado en permisos).
//
// Fuente única para esos 4 lugares. roadmap.component.ts e
// informe-tecnico-bandeja.component.ts ya tenían su propio mapeo correcto
// desde el fix original — no se tocan acá para no arriesgar una regresión
// en esta tanda; si se vuelve a tocar el nombre de un rol, actualizar
// también esos dos archivos.
export const ROLE_LABELS: Record<string, string> = {
  superadmin: 'Superadmin',
  gerente: 'Gerente',
  operativo: 'Operativo',
  contable: 'Contable',
  cliente: 'Cliente',
  coordinador_comercial: 'Director de Crédito',
  oficial_cumplimiento: 'Oficial de Cumplimiento',
  comite_credito: 'Comité de Crédito',
  tesoreria: 'Tesorería',
  ingeniero: 'Ingeniero',
};

/** Label legible de un slug de rol. Si el slug no está en el catálogo
 * (rol nuevo agregado sin actualizar este mapa), cae al transform genérico
 * anterior en vez de mostrar el slug crudo. */
export function getRoleLabel(slug: string | null | undefined): string {
  if (!slug) {
    return '';
  }
  return ROLE_LABELS[slug] ?? slug.split('_').join(' ').replace(/\b\w/g, c => c.toUpperCase());
}
