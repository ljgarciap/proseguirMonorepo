<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ConciliacionSusuerte;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConciliacionSusuerteTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $cc = \App\Models\DocumentType::where('codigo', 'CC')->first() 
            ?? \App\Models\DocumentType::first() 
            ?? \App\Models\DocumentType::create(['nombre' => 'Cedula', 'codigo' => 'CC']);

        $this->user = User::create([
            'name' => 'Operativo Test User',
            'email' => 'operativo.test.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'test_op_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['operativo']
        ]);
    }

    public function test_authenticated_user_can_access_history()
    {
        // Seed database with a record for the user
        ConciliacionSusuerte::create([
            'user_id' => $this->user->id,
            'total_amount' => 150000.00,
            'matched_count' => 10,
            'unmatched_susuerte_count' => 2,
            'unmatched_bank_count' => 1,
            'details' => [],
            'conciliated_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/conciliaciones-susuerte/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'total'
            ]);
    }

    public function test_authenticated_user_can_trigger_new_conciliation()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/conciliaciones-susuerte/new');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Nueva conciliación iniciada'
            ]);
    }

    public function test_authenticated_user_can_update_observations()
    {
        $conciliacion = ConciliacionSusuerte::create([
            'user_id' => $this->user->id,
            'total_amount' => 1000.00,
            'matched_count' => 1,
            'details' => [
                [
                    'Status' => 'CONCILIADO',
                    'Date (Susuerte)' => '2026-06-04',
                    'Date (Bank)' => '2026-06-04',
                    'Amount' => 1000.00,
                    'Description (Susuerte)' => 'Test Susuerte',
                    'Description (Bank)' => 'Test Bank',
                    'Observations' => ''
                ]
            ],
            'conciliated_at' => now(),
        ]);

        $updatedDetails = [
            [
                'Status' => 'CONCILIADO',
                'Date (Susuerte)' => '2026-06-04',
                'Date (Bank)' => '2026-06-04',
                'Amount' => 1000.00,
                'Description (Susuerte)' => 'Test Susuerte',
                'Description (Bank)' => 'Test Bank',
                'Observations' => 'Updated test observation'
            ]
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/conciliaciones-susuerte/{$conciliacion->id}", [
                'details' => $updatedDetails
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Observaciones guardadas con éxito'
            ])
            ->assertJsonStructure(['download_url']);

        $this->assertEquals(
            'Updated test observation',
            ConciliacionSusuerte::find($conciliacion->id)->details[0]['Observations']
        );
    }

    public function test_unauthenticated_user_cannot_access_history()
    {
        $response = $this->getJson('/api/conciliaciones-susuerte/history');
        $response->assertStatus(401);
    }
}
