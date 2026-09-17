<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests are sent to the login screen, employees to their attendance
     * screen and administrators to the administrator dashboard.
     */
    public function test_the_application_redirects_to_the_right_entry_point(): void
    {
        $this->get('/')->assertRedirect(route('login', absolute: false));

        $this->actingAs(User::factory()->create())
            ->get('/')->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/')->assertRedirect(route('admin.dashboard', absolute: false));
    }
}
