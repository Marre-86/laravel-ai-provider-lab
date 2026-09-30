<?php

use App\Ai\Agents\Advisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);
});

it('displays the generated response', function () {
    Advisor::fake([['value' => 'Laravel is a PHP framework.']]);

    post(route('prompts.store'), ['prompt' => 'Explain Laravel routing', 'provider' => 'ollama'])
        ->assertRedirect('/')
        ->assertSessionHas('response', 'Laravel is a PHP framework.');
});

it('requires a prompt', function () {
    post(route('prompts.store'), ['prompt' => '', 'provider' => 'ollama'])->assertSessionHasErrors('prompt');
});

it('requires a configured provider', function () {
    post(route('prompts.store'), ['prompt' => 'Hello', 'provider' => 'unknown'])->assertSessionHasErrors('provider');
});
