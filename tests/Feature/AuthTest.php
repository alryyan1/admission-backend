<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'username' => 'nurse1',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'nurse1',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'nurse1',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'nurse1',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'nurse1',
            'password' => 'secret123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'nurse1',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }
}
