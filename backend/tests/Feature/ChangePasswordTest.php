<?php

namespace Tests\Feature;

use App\Mail\PasswordCambiadaMail;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-317: cambio de contraseña propio + confirmación por correo.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->user = User::create([
            'name' => 'Usuario Test',
            'email' => 'usuario.test.' . uniqid() . '@test.com',
            'password' => bcrypt('password_actual'),
            'numero_documento' => 'user_doc_' . uniqid(),
            'tipo_documento_id' => $docCC->id,
            'roles' => ['operativo'],
        ]);
    }

    public function test_change_password_successfully_sends_confirmation_email(): void
    {
        Mail::fake();
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'password_actual',
            'new_password' => 'password_nueva123',
            'new_password_confirmation' => 'password_nueva123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Contraseña actualizada correctamente']);

        Mail::assertSent(PasswordCambiadaMail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });

        $this->assertDatabaseHas('activity_logs', [
            'usuario_id' => $this->user->id,
            'accion' => 'auth.password_cambiada',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'usuario_id' => $this->user->id,
            'accion' => 'auth.password_cambiada_notificacion_enviada',
        ]);
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        Mail::fake();
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'contraseña_equivocada',
            'new_password' => 'password_nueva123',
            'new_password_confirmation' => 'password_nueva123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
        $this->assertStringContainsString('incorrecta', $response->json('message'));
        Mail::assertNothingSent();
    }

    public function test_change_password_rejects_new_password_equal_to_current(): void
    {
        Mail::fake();
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'password_actual',
            'new_password' => 'password_actual',
            'new_password_confirmation' => 'password_actual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
        $this->assertStringContainsString('diferente', $response->json('message'));
        Mail::assertNothingSent();
    }

    public function test_change_password_rejects_mismatched_confirmation(): void
    {
        Mail::fake();
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'password_actual',
            'new_password' => 'password_nueva123',
            'new_password_confirmation' => 'password_distinta',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
        Mail::assertNothingSent();
    }
}
