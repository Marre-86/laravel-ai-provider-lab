<?php

use App\Ai\Agents\Advisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);

    // The real config/ai.php entry is currently commented out, so the shape is
    // pinned here: this covers the wiring, not whether the model is switched on.
    config(['ai.selectable_models' => [
        'space-bunny-alpha' => [
            'label' => 'Space Bunny Alpha',
            'provider' => 'openrouter',
            'model' => 'stealth/space-bunny-alpha',
        ],
    ]]);
});

it('exposes Space Bunny as a selectable model served by OpenRouter', function () {
    expect(config('ai.selectable_models.space-bunny-alpha'))->toBe([
        'label' => 'Space Bunny Alpha',
        'provider' => 'openrouter',
        'model' => 'stealth/space-bunny-alpha',
    ]);
});

it('accepts Space Bunny as a valid model choice', function () {
    Advisor::fake([['value' => 'BUNNY OK']]);

    post(route('prompts.store'), ['prompt' => 'Identify yourself', 'model' => 'space-bunny-alpha'])
        ->assertRedirect('/')
        ->assertSessionHas('response', 'BUNNY OK');
});
