<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_the_login_screen_for_guests(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee('Sign in');
        $response->assertSee('name="email"', false);
    }

    public function test_home_redirects_authenticated_users_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
