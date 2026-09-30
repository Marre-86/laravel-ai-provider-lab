<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    User::factory()->create(['id' => 1]);

    $response = $this->get('/');
    $response = $this->get('/');

    $response->assertStatus(200);
});
