<?php

use App\Ai\Agents\Reviewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);
});

function reviewPayload(array $overrides = []): array
{
    return array_merge([
        'prompt' => 'Who won the last US Open?',
        'instruction' => 'Compare the answers.',
        'provider' => 'gemini',
        'responses' => [
            'ollama' => 'Novak Djokovic.',
            'gemini' => 'Coco Gauff.',
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

it('labels each answer with the provider that produced it', function () {
    Reviewer::fake(['Review complete.']);

    $this->postJson(route('prompts.review'), reviewPayload())->assertOk();

    Reviewer::assertPrompted(fn ($recorded) => str_contains($recorded->prompt, '### Ollama')
        && str_contains($recorded->prompt, '### Gemini'));
});

it('leaves out providers that produced no answer', function () {
    Reviewer::fake(['Review complete.']);

    $this->postJson(route('prompts.review'), reviewPayload([
        'responses' => ['ollama' => 'Novak Djokovic.', 'gemini' => null],
    ]))->assertOk();

    Reviewer::assertPrompted(fn ($recorded) => ! str_contains($recorded->prompt, '### Gemini'));
});

it('refuses to review when no provider produced an answer', function () {
    Reviewer::fake();

    $this->postJson(route('prompts.review'), reviewPayload([
        'responses' => ['ollama' => null, 'gemini' => '   '],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('responses');

    Reviewer::assertNeverPrompted();
});

it('refuses a provider that is not selectable', function () {
    Reviewer::fake();

    $this->postJson(route('prompts.review'), reviewPayload(['provider' => 'openai']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('provider');

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

it('offers every selectable provider to review with', function () {
    $content = $this->get('/')->assertOk()->getContent();

    foreach (config('ai.selectable_providers') as $key => $provider) {
        expect($content)->toContain('<option value="'.$key.'">'.$provider['label'].' &mdash; '.$provider['model']);
    }
});

it('keeps the review block hidden until the providers have finished answering', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toMatch('/id="review-panel"[^>]*\bhidden\b/');

    $script = file_get_contents(resource_path('js/welcome.js'));

    $streamed = strpos($script, 'Promise.allSettled');
    $revealed = strpos($script, "\$('#review-panel').removeClass('hidden')");

    // The panel may only appear once every provider stream has settled.
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
