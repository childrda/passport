<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_home_to_admin(): void
    {
        $this->get('/')
            ->assertRedirect('/admin');
    }
}
