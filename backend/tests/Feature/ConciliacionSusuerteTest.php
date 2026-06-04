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
        // Create an operative user
        $this->user = User::factory()->create([
            'role' => 'operativo'
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

    public function test_unauthenticated_user_cannot_access_history()
    {
        $response = $this->getJson('/api/conciliaciones-susuerte/history');
        $response->assertStatus(401);
    }
}
