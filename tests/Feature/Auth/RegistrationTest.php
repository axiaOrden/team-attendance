<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;
    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'employee_id' => 'EMP777',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'employee_id' => 'EMP777',
            'role' => \App\Models\User::ROLE_EMPLOYEE,
        ]);
    }

    public function test_employee_id_must_be_unique(): void
    {
        User::factory()->create(['employee_id' => 'EMP777']);

        $response = $this->post('/register', [
            'name' => 'Another User',
            'employee_id' => 'EMP777',
            'email' => 'another@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertGuest();
    }

    public function test_employee_id_is_required(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Id',
            'email' => 'noid@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertGuest();
    }
}
