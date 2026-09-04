import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

/**
 * RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md) — fallback universal
 * de roleGuard. Antes, el rol activo sin ningún redirect específico caía a
 * `/dashboard` a ciegas; para los 10 roles originales eso era seguro
 * (los 4 que llegaban ahí siempre tenían el permiso 'dashboard'), pero un
 * rol NUEVO creado desde /roles sin ese permiso quedaba en un loop de
 * redirect infinito (/dashboard lo rechaza → lo vuelve a mandar a
 * /dashboard). Esta pantalla no requiere ningún permiso — es el destino
 * seguro cuando el rol activo no tiene ninguna pantalla "home" conocida.
 */
@Component({
  selector: 'app-sin-acceso',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="wrapper">
      <div class="card">
        <span class="material-symbols-outlined icon">block</span>
        <h1>Sin acceso configurado</h1>
        <p>Tu rol no tiene ninguna pantalla asignada todavía. Contactá a tu administrador para que te asigne permisos desde Configuración → Roles y Permisos.</p>
      </div>
    </div>
  `,
  styles: [`
    .wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #F4F7FE; padding: 2rem; }
    .card { background: #fff; border-radius: 16px; padding: 3rem; max-width: 420px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
    .icon { font-size: 48px; color: #CBD5E0; }
    h1 { font-size: 1.25rem; color: var(--primary); margin: 1rem 0 0.5rem; }
    p { color: #64748B; font-size: 0.9rem; line-height: 1.5; margin: 0; }
  `]
})
export class SinAccesoComponent {}
