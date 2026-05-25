import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { FormsModule } from '@angular/forms';
import { AsignacionesComponent } from './asignaciones.component';
import { environment } from '../../../environments/environment';

describe('AsignacionesComponent', () => {
  let component: AsignacionesComponent;
  let fixture: ComponentFixture<AsignacionesComponent>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, FormsModule, AsignacionesComponent]
    }).compileComponents();

    fixture = TestBed.createComponent(AsignacionesComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should create the component and fetch initial data lists', () => {
    expect(component).toBeTruthy();
    
    fixture.detectChanges(); // Triggers ngOnInit() which calls loadAsignaciones() and loadActiveNotifications()
    
    // GET al iniciarse para asignaciones
    const reqAsig = httpMock.expectOne(`${component.apiUrl}`);
    expect(reqAsig.request.method).toBe('GET');
    reqAsig.flush([]);

    // GET al iniciarse para notificaciones activas
    const reqNotif = httpMock.expectOne(`${environment.apiUrl}/notificaciones`);
    expect(reqNotif.request.method).toBe('GET');
    reqNotif.flush([]);
  });

  it('should load active notifications and filter inactive ones', () => {
    const mockNotifications = [
      { id: 1, nombre: 'Activa 1', mensaje: 'msg', activo: true },
      { id: 2, nombre: 'Inactiva', mensaje: 'msg', activo: false },
      { id: 3, nombre: 'Activa 2', mensaje: 'msg', activo: true }
    ];

    fixture.detectChanges(); // Triggers ngOnInit()

    // Responder al request de asignaciones
    const reqAsig = httpMock.expectOne(`${component.apiUrl}`);
    reqAsig.flush([]);

    // Responder al request de notificaciones
    const reqNotif = httpMock.expectOne(`${environment.apiUrl}/notificaciones`);
    reqNotif.flush(mockNotifications);

    // Debió filtrar las inactivas (solo deben quedar las activas id 1 y id 3)
    expect(component.activeNotifications.length).toBe(2);
    expect(component.activeNotifications[0].id).toBe(1);
    expect(component.activeNotifications[1].id).toBe(3);
  });
});
