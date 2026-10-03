<?php

use App\Ai\Agents\Advisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);

    // Pinned so commenting entries out in config/ai.php cannot break these.
    config(['ai.selectable_models' => [
        'gemma3-12b' => ['label' => 'Gemma 3 12B', 'provider' => 'ollama', 'model' => 'gemma3:12b'],
        'gemini-3-flash-preview' => ['label' => 'Gemini 3 Flash', 'provider' => 'gemini', 'model' => 'gemini-3-flash-preview'],
    ]]);
});

it('refuses a model that is not selectable', function () {
    Advisor::fake();

    $this->postJson(route('prompts.stream'), ['prompt' => 'Hello', 'model' => 'openai'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('model');

    Advisor::assertNeverPrompted();
});

it('refuses the retired provider field', function () {
    Advisor::fake();

    $this->postJson(route('prompts.stream'), ['prompt' => 'Hello', 'provider' => 'gemma3-12b'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('model');

    Advisor::assertNeverPrompted();
});

it('streams a selected model', function () {
    Advisor::fake(['Laravel is a PHP framework.']);

    $this->postJson(route('prompts.stream'), ['prompt' => 'Explain Laravel routing', 'model' => 'gemma3-12b'])
        ->assertOk();

    Advisor::assertPrompted(fn ($recorded) => $recorded->prompt === 'Explain Laravel routing');
});
