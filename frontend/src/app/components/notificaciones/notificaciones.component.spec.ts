import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { FormsModule } from '@angular/forms';
import { NotificacionesComponent } from './notificaciones.component';

describe('NotificacionesComponent', () => {
  let component: NotificacionesComponent;
  let fixture: ComponentFixture<NotificacionesComponent>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, FormsModule, NotificacionesComponent]
    }).compileComponents();

    fixture = TestBed.createComponent(NotificacionesComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should create the component and fetch initial notifications', () => {
    expect(component).toBeTruthy();
    
    fixture.detectChanges(); // Triggers ngOnInit() which calls loadNotificaciones()
    
    // Debería realizar un GET al inicializarse
    const req = httpMock.expectOne(`${component.apiUrl}`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('should load notifications from the API successfully', () => {
    const mockNotifications = [
      { id: 1, nombre: 'Pago Confirmado', mensaje: 'Mensaje de pago...', activo: true },
      { id: 2, nombre: 'Alerta de Tasas', mensaje: 'Tasas cambiadas...', activo: false }
    ];

    fixture.detectChanges(); // Triggers ngOnInit()

    const req = httpMock.expectOne(`${component.apiUrl}`);
    req.flush(mockNotifications);

    expect(component.notificaciones.length).toBe(2);
    expect(component.notificaciones[0].nombre).toBe('Pago Confirmado');
    expect(component.notificaciones[1].activo).toBeFalse();
  });
});
