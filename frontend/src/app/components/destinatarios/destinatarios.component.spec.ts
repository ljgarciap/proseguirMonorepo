import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { FormsModule } from '@angular/forms';
import { DestinatariosComponent } from './destinatarios.component';

describe('DestinatariosComponent', () => {
  let component: DestinatariosComponent;
  let fixture: ComponentFixture<DestinatariosComponent>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, FormsModule, DestinatariosComponent]
    }).compileComponents();

    fixture = TestBed.createComponent(DestinatariosComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should create the component and fetch initial recipients', () => {
    expect(component).toBeTruthy();
    
    fixture.detectChanges(); // Triggers ngOnInit() which calls loadDestinatarios()
    
    // Debería realizar un GET al inicializarse
    const req = httpMock.expectOne(`${component.apiUrl}`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('should load destinatarios from the API successfully', () => {
    const mockRecipients = [
      { id: 1, nombre: 'Ana', email: 'ana@test.com', activo: true },
      { id: 2, nombre: 'Zacarias', email: 'zacarias@test.com', activo: false }
    ];

    fixture.detectChanges(); // Triggers ngOnInit()

    const req = httpMock.expectOne(`${component.apiUrl}`);
    req.flush(mockRecipients);

    expect(component.destinatarios.length).toBe(2);
    expect(component.destinatarios[0].nombre).toBe('Ana');
    expect(component.destinatarios[1].activo).toBeFalse();
  });
});
