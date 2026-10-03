<?php

use App\Ai\Agents\Advisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);

    // Pinned so that commenting this entry out in config/ai.php cannot break the
    // test: the point here is the submission flow, not which model is enabled.
    config(['ai.selectable_models' => [
        'gemma3-12b' => ['label' => 'Gemma 3 12B', 'provider' => 'ollama', 'model' => 'gemma3:12b'],
    ]]);
});

it('displays the generated response', function () {
    Advisor::fake([['value' => 'Laravel is a PHP framework.']]);

    post(route('prompts.store'), ['prompt' => 'Explain Laravel routing', 'model' => 'gemma3-12b'])
        ->assertRedirect('/')
        ->assertSessionHas('response', 'Laravel is a PHP framework.');
});

it('requires a prompt', function () {
    post(route('prompts.store'), ['prompt' => '', 'model' => 'gemma3-12b'])->assertSessionHasErrors('prompt');
});

it('requires a configured model', function () {
    post(route('prompts.store'), ['prompt' => 'Hello', 'model' => 'unknown'])->assertSessionHasErrors('model');
});
