import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ClientesComponent } from './clientes.component';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { FormsModule } from '@angular/forms';

describe('ClientesComponent', () => {
  let component: ClientesComponent;
  let fixture: ComponentFixture<ClientesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [
        ClientesComponent,
        HttpClientTestingModule,
        FormsModule
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(ClientesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('should validate email addresses correctly', () => {
    // Access private/internal validation method using index signature or public test helper
    const validEmail = 'test@proseguir.com';
    const invalidEmail = 'test-invalid-email';
    
    expect((component as any).isValidEmail(validEmail)).toBeTrue();
    expect((component as any).isValidEmail(invalidEmail)).toBeFalse();
  });

  it('should change field visibility according to client type', () => {
    // Mock type parameters
    component.tipoPersonas = [
      { id: 1, nombre: 'Persona Natural', codigo: 'NATURAL' },
      { id: 2, nombre: 'Persona Jurídica', codigo: 'JURIDICA' }
    ];

    // Change to natural
    component.form.tipo_persona_id = 1;
    expect(component.getSelectedTipoPersonaCodigo()).toBe('NATURAL');

    // Change to juridical
    component.form.tipo_persona_id = 2;
    expect(component.getSelectedTipoPersonaCodigo()).toBe('JURIDICA');
  });

  it('should clear dynamic fields when switching type', () => {
    component.tipoPersonas = [
      { id: 1, nombre: 'Persona Natural', codigo: 'NATURAL' },
      { id: 2, nombre: 'Persona Jurídica', codigo: 'JURIDICA' }
    ];

    component.form.nombres = 'Carlos';
    component.form.primer_apellido = 'Perez';
    component.form.tipo_persona_id = 2; // Switch to Juridical

    component.onTipoPersonaChange();

    expect(component.form.nombres).toBe('');
    expect(component.form.primer_apellido).toBe('');
  });
});
