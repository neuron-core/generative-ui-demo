<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('chat'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_chat()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('chat'));
        $response->assertOk();
    }
}
