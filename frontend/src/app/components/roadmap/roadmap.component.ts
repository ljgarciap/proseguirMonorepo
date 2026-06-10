import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

interface Feature {
  name: string;
  description: string;
  route: string;
  area: 'Operaciones' | 'Administración' | 'Sistema' | 'Configuración Documentos' | 'Notificaciones' | 'Configuración' | 'Portal Cliente';
  roles: string[];
  icon: string;
  status: 'completado' | 'en_progreso' | 'planificado';
}

@Component({
  selector: 'app-roadmap',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './roadmap.component.html',
  styleUrls: ['./roadmap.component.css']
})
export class RoadmapComponent implements OnInit {
  allRoles = [
    { key: 'superadmin', label: 'Superadmin', color: '#6366F1' },
    { key: 'gerente', label: 'Gerente', color: '#3B82F6' },
    { key: 'operativo', label: 'Operativo', color: '#10B981' },
    { key: 'contable', label: 'Contable', color: '#F59E0B' },
    { key: 'cliente', label: 'Cliente', color: '#EC4899' },
    { key: 'coordinador_comercial', label: 'Coordinador Comercial', color: '#8B5CF6' },
    { key: 'oficial_cumplimiento', label: 'Oficial de Cumplimiento', color: '#06B6D4' },
    { key: 'comite_credito', label: 'Comité de Crédito', color: '#EF4444' },
    { key: 'tesoreria', label: 'Tesorería', color: '#14B8A6' }
  ];

  areas = [
    'Todos',
    'Operaciones',
    'Administración',
    'Sistema',
    'Configuración Documentos',
    'Notificaciones',
    'Configuración',
    'Portal Cliente'
  ];

  selectedArea: string = 'Todos';
  selectedRole: string = 'Todos';
  searchQuery: string = '';

  features: Feature[] = [
    // Operaciones
    {
      name: 'Dashboard Factoring',
      description: 'Indicadores clave como Volumen Financiado (suma valor presente), Valor Vencido, y accesos directos al listado consolidado de saldos.',
      route: '/dashboard',
      area: 'Operaciones',
      roles: ['superadmin', 'gerente', 'operativo', 'contable'],
      icon: 'dashboard',
      status: 'completado'
    },
    {
      name: 'Crédito Ordinario',
      description: 'Flujo estructurado BPMN para solicitudes de crédito ordinario con aprobaciones multinivel (Comercial, Oficial, Comité, Tesorería, Gerente).',
      route: '/creditos',
      area: 'Operaciones',
      roles: ['cliente', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin'],
      icon: 'payments',
      status: 'completado'
    },
    {
      name: 'Registro de Solicitudes de Crédito',
      description: 'Formalización de solicitudes de crédito de clientes a partir de necesidades detectadas en visitas comerciales o de forma manual, vinculando presets de documentos.',
      route: '/solicitudes-credito',
      area: 'Operaciones',
      roles: ['coordinador_comercial', 'gerente', 'operativo', 'superadmin'],
      icon: 'assignment_turned_in',
      status: 'completado'
    },
    {
      name: 'Base de Datos de Factoring',
      description: 'Historial completo de planillas importadas, control de cartera y seguimiento del ciclo de vida de cada operación.',
      route: '/sheets',
      area: 'Operaciones',
      roles: ['operativo'],
      icon: 'database',
      status: 'completado'
    },
    {
      name: 'Subir Operación',
      description: 'Carga masiva de planillas de factoring desde plantillas Excel al sistema con validaciones de campos.',
      route: '/upload',
      area: 'Operaciones',
      roles: ['operativo'],
      icon: 'upload_file',
      status: 'completado'
    },
    {
      name: 'Validación de Documentos de Clientes',
      description: 'Módulo interactivo para que el Operador o Gerente valide los soportes cargados por los clientes y marque su aprobación/rechazo.',
      route: '/validation',
      area: 'Operaciones',
      roles: ['operativo', 'gerente'],
      icon: 'rule',
      status: 'completado'
    },
    {
      name: 'Bandeja Interna',
      description: 'Buzón para el tránsito de documentos internos listos para revisión contable y aprobación de la gerencia.',
      route: '/internal-docs',
      area: 'Operaciones',
      roles: ['superadmin', 'gerente', 'operativo', 'contable'],
      icon: 'mail',
      status: 'completado'
    },
    {
      name: 'Revisión y Firma de Mandatos',
      description: 'Ciclo de vida de contratos de mandato y soportes jurídicos, incluyendo su generación y firma electrónica simplificada.',
      route: '/mandatos',
      area: 'Operaciones',
      roles: ['cliente', 'superadmin', 'operativo'],
      icon: 'contract',
      status: 'completado'
    },

    // Administración
    {
      name: 'Conciliación Susuerte',
      description: 'Algoritmo de cruce automático para reportes PDF de Susuerte y bases internas, arrojando diferencias y conciliaciones exitosas.',
      route: '/conciliacion-susuerte',
      area: 'Administración',
      roles: ['superadmin', 'gerente', 'operativo', 'contable'],
      icon: 'fact_check',
      status: 'completado'
    },
    {
      name: 'Registro de Clientes',
      description: 'CRUD de clientes naturales/jurídicos con creación automática de credenciales de usuario por defecto (cédula/NIT).',
      route: '/clientes',
      area: 'Administración',
      roles: ['superadmin', 'gerente', 'operativo'],
      icon: 'group',
      status: 'completado'
    },
    {
      name: 'Registro de Visita a Cliente',
      description: 'Registro detallado de visitas comerciales y formulario dinámico para perfilar necesidades de crédito ordinario detectadas.',
      route: '/visitas',
      area: 'Administración',
      roles: ['superadmin', 'gerente', 'operativo'],
      icon: 'chat_bubble',
      status: 'completado'
    },

    // Sistema
    {
      name: 'Auditoría y Trazabilidad',
      description: 'Registro inmutable de logs de auditoría para rastrear acciones críticas en base de datos realizadas por cualquier usuario.',
      route: '/logs',
      area: 'Sistema',
      roles: ['superadmin', 'gerente', 'operativo', 'contable'],
      icon: 'shield_person',
      status: 'completado'
    },

    // Configuración Documentos
    {
      name: 'Solicitud de Documentos',
      description: 'Generación manual o automática de requisiciones de documentos/soportes dirigidos a clientes específicos.',
      route: '/document-requests',
      area: 'Configuración Documentos',
      roles: ['superadmin', 'operativo'],
      icon: 'checklist',
      status: 'completado'
    },
    {
      name: 'Configuración de Requisitos',
      description: 'Editor de tipos de documentos, requisitos de obligatoriedad, vigencias y plantillas de carpetas (presets).',
      route: '/document-config',
      area: 'Configuración Documentos',
      roles: ['superadmin', 'operativo'],
      icon: 'settings_applications',
      status: 'completado'
    },

    // Notificaciones
    {
      name: 'Destinatarios de Alertas',
      description: 'Configuración de buzones de correo y canales receptores de alertas del sistema sobre vencimiento de plazos.',
      route: '/destinatarios',
      area: 'Notificaciones',
      roles: ['superadmin'],
      icon: 'mail_lock',
      status: 'completado'
    },
    {
      name: 'Notificaciones Automatizadas',
      description: 'Configuración de plantillas, disparadores y bitácora histórica de envío de alertas por correo.',
      route: '/notificaciones',
      area: 'Notificaciones',
      roles: ['superadmin'],
      icon: 'notifications_active',
      status: 'completado'
    },
    {
      name: 'Asignaciones de Alertas',
      description: 'Asociación de destinatarios clave a flujos operacionales y tipos de alertas de cartera.',
      route: '/asignaciones',
      area: 'Notificaciones',
      roles: ['superadmin'],
      icon: 'assignment_ind',
      status: 'completado'
    },

    // Configuración
    {
      name: 'Gestión de Usuarios',
      description: 'Panel de administración de cuentas del sistema para crear, inactivar y cambiar roles o perfiles asignados.',
      route: '/users',
      area: 'Configuración',
      roles: ['superadmin'],
      icon: 'group',
      status: 'completado'
    },
    {
      name: 'Parámetros del Sistema',
      description: 'CRUD de tablas paramétricas del negocio, tales como tipos de personas, estados, tipos de créditos y amortizaciones.',
      route: '/parameters',
      area: 'Configuración',
      roles: ['superadmin'],
      icon: 'settings_applications',
      status: 'completado'
    },
    {
      name: 'Limpieza y Reseteo BD',
      description: 'Módulo técnico para truncar tablas por módulos, restaurar el esquema completo de la BD y diagnosticar la integridad estructural.',
      route: '/db-cleaner',
      area: 'Configuración',
      roles: ['superadmin'],
      icon: 'restart_alt',
      status: 'completado'
    },

    // Portal Cliente
    {
      name: 'Mis Cargas (Portal Cliente)',
      description: 'Espacio de autoservicio para que el cliente cargue la documentación jurídica y financiera solicitada.',
      route: '/client-upload',
      area: 'Portal Cliente',
      roles: ['cliente'],
      icon: 'folder_shared',
      status: 'completado'
    }
  ];

  filteredFeatures: Feature[] = [];

  ngOnInit() {
    this.applyFilters();
  }

  applyFilters() {
    this.filteredFeatures = this.features.filter(f => {
      const matchesArea = this.selectedArea === 'Todos' || f.area === this.selectedArea;
      const matchesRole = this.selectedRole === 'Todos' || 
                          this.selectedRole === 'superadmin' || 
                          f.roles.includes(this.selectedRole);
      const matchesSearch = f.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            f.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            f.route.toLowerCase().includes(this.searchQuery.toLowerCase());
      return matchesArea && matchesRole && matchesSearch;
    });
  }

  getRoleLabel(roleKey: string): string {
    const role = this.allRoles.find(r => r.key === roleKey);
    return role ? role.label : roleKey;
  }

  getRoleColor(roleKey: string): string {
    const role = this.allRoles.find(r => r.key === roleKey);
    return role ? role.color : '#718096';
  }

  getRolesForFeature(feature: Feature): string[] {
    if (!feature.roles.includes('superadmin')) {
      return ['superadmin', ...feature.roles];
    }
    return feature.roles;
  }

  isRoleInvolved(feature: Feature, roleKey: string): boolean {
    if (roleKey === 'superadmin') return true; // Superadmin has access to all features by design
    return feature.roles.includes(roleKey);
  }
}
