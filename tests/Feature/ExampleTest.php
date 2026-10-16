<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_dashboard_opens_for_a_signed_in_user(): void
    {
        $this->actingAsSuperAdmin()->get('/')->assertOk();
    }
}
