<?php

use App\Ai\Agents\Reviewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);

    // Pinned rather than read from config/ai.php: which models are switched on
    // locally is a preference, and these tests must not fail when one is
    // commented out. The application code is still driven by the same shape.
    config(['ai.selectable_models' => [
        'gemma3-12b' => ['label' => 'Gemma 3 12B', 'provider' => 'ollama', 'model' => 'gemma3:12b'],
        'gemini-3-flash-preview' => ['label' => 'Gemini 3 Flash', 'provider' => 'gemini', 'model' => 'gemini-3-flash-preview'],
    ]]);
});

function reviewPayload(array $overrides = []): array
{
    return array_merge([
        'prompt' => 'Who won the last US Open?',
        'instruction' => 'Compare the answers.',
        'model' => 'gemini-3-flash-preview',
        'responses' => [
            'gemma3-12b' => 'Novak Djokovic.',
            'gemini-3-flash-preview' => 'Coco Gauff.',
        ],
    ], $overrides);
}

it('sends the original prompt, the instruction and every collected answer to the reviewer', function () {
    Reviewer::fake(['Review complete.']);

    $this->postJson(route('prompts.review'), reviewPayload())->assertOk();

    Reviewer::assertPrompted(fn ($recorded) => str_contains($recorded->prompt, 'Who won the last US Open?')
        && str_contains($recorded->prompt, 'Compare the answers.')
        && str_contains($recorded->prompt, 'Novak Djokovic.')
        && str_contains($recorded->prompt, 'Coco Gauff.'));
});

it('labels each answer with the model that produced it', function () {
    Reviewer::fake(['Review complete.']);

    $this->postJson(route('prompts.review'), reviewPayload())->assertOk();

    // The labels come from the configuration, so the assertion tracks them.
    Reviewer::assertPrompted(fn ($recorded) => str_contains($recorded->prompt, '### Gemma 3 12B')
        && str_contains($recorded->prompt, '### Gemini 3 Flash'));
});

it('leaves out models that produced no answer', function () {
    Reviewer::fake(['Review complete.']);

    $this->postJson(route('prompts.review'), reviewPayload([
        'responses' => ['gemma3-12b' => 'Novak Djokovic.', 'gemini-3-flash-preview' => null],
    ]))->assertOk();

    Reviewer::assertPrompted(fn ($recorded) => ! str_contains($recorded->prompt, '### Gemini'));
});

it('refuses to review when no model produced an answer', function () {
    Reviewer::fake();

    $this->postJson(route('prompts.review'), reviewPayload([
        'responses' => ['gemma3-12b' => null, 'gemini-3-flash-preview' => '   '],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('responses');

    Reviewer::assertNeverPrompted();
});

it('refuses a model that is not selectable', function () {
    Reviewer::fake();

    $this->postJson(route('prompts.review'), reviewPayload(['model' => 'openai']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('model');

    Reviewer::assertNeverPrompted();
});

it('requires an instruction to review against', function () {
    Reviewer::fake();

    $this->postJson(route('prompts.review'), reviewPayload(['instruction' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('instruction');

    Reviewer::assertNeverPrompted();
});

it('renders the review block with an instruction seeded from the configuration', function () {
    config(['ai.evaluation_prompt' => 'Seeded from the configuration.']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Seeded from the configuration.', escape: false)
        ->assertSee('id="review-instruction"', escape: false)
        ->assertSee('id="review-button"', escape: false)
        ->assertSee('Evaluate responses', escape: false);
});

it('offers every selectable model to review with', function () {
    $content = $this->get('/')->assertOk()->getContent();

    foreach (config('ai.selectable_models') as $key => $model) {
        expect($content)->toContain('<option value="'.$key.'">'.$model['label'].' &mdash; '.$model['model']);
    }
});

it('keeps the review block hidden until the models have finished answering', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toMatch('/id="review-panel"[^>]*\bhidden\b/');

    $script = file_get_contents(resource_path('js/welcome.js'));

    $streamed = strpos($script, 'Promise.allSettled');
    $revealed = strpos($script, "\$('#review-panel').removeClass('hidden')");

    // The panel may only appear once every model stream has settled.
    expect($streamed)->not->toBeFalse()
        ->and($revealed)->not->toBeFalse()
        ->and($revealed)->toBeGreaterThan($streamed);
});

it('keeps the review output collapsed until Evaluate responses is pressed', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toMatch('/id="review-output"[^>]*\bhidden\b/');

    $script = file_get_contents(resource_path('js/welcome.js'));

    $revealed = strpos($script, "\$output.removeClass('hidden')");

    // The output opens on the click, before anything is written into it.
    expect($revealed)->not->toBeFalse()
        ->and($revealed)->toBeLessThan(strpos($script, '$output.empty().append(buildSpinner())'));
});
