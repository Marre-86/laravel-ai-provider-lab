<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);
});

it('renders a button with the model name for every selectable provider', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(array_merge(...array_map(
            fn (array $provider, string $key) => [$key, $provider['label'], $provider['model']],
            config('ai.selectable_providers'),
            array_keys(config('ai.selectable_providers')),
        )));
});

it('preselects the provider stored in the session', function () {
    $this->withSession(['provider' => 'gemini'])
        ->get('/')
        ->assertOk()
        ->assertSee('<input id="provider" name="provider" type="hidden" value="gemini">', escape: false);
});

it('does not render a standalone response container', function () {
    $this->get('/')->assertOk()->assertDontSee('<div id="response">', escape: false);
});
