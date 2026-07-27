import { bootstrapApplication } from '@angular/platform-browser';
import { registerLocaleData } from '@angular/common';
import localeEsCO from '@angular/common/locales/es-CO';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';

// SCRUM-154: sin este registro, todo pipe que use el locale 'es-CO'
// (number/percent/currency) revienta en runtime con NG0701 y renderiza
// vacío — afecta los totales de Informe Técnico y otras 22 vistas en toda
// la app que usan 'es-CO' explícitamente.
registerLocaleData(localeEsCO);

bootstrapApplication(AppComponent, appConfig)
    .catch((err) => console.error(err));
