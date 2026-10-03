<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);

    // See ReviewResponsesTest: the tests pin the model list so that commenting an
    // entry out in config/ai.php cannot break them.
    config(['ai.selectable_models' => [
        'gemma3-12b' => ['label' => 'Gemma 3 12B', 'provider' => 'ollama', 'model' => 'gemma3:12b'],
        'gemini-3-flash-preview' => ['label' => 'Gemini 3 Flash', 'provider' => 'gemini', 'model' => 'gemini-3-flash-preview'],
    ]]);
});

function welcomeData(string $content): array
{
    preg_match('/<script type="application\/json" id="welcome-data">(.*?)<\/script>/s', $content, $matches);

    return json_decode($matches[1], true);
}

it('passes every selectable model and its model to the response panes', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect(welcomeData($content)['models'])->toBe(config('ai.selectable_models'));
});

it('passes the stream endpoint and a pane entry per selectable model to the panes', function () {
    $content = $this->get('/')->assertOk()->getContent();

    $data = welcomeData($content);

    expect($data['streamUrl'])->toBe(route('prompts.stream'))
        ->and(array_keys($data['exchanges']))->toBe(array_keys(config('ai.selectable_models')))
        ->and($data['exchanges']['gemini-3-flash-preview'])->toBe(['prompt' => null, 'response' => null]);
});

it('renders the panes container as a grid and no longer renders model buttons', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="exchange-container" class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4"', escape: false)
        ->assertDontSee('provider-button', escape: false);
});

it('preselects the model stored in the session', function () {
    $this->withSession(['model' => 'gemini-3-flash-preview'])
        ->get('/')
        ->assertOk()
        ->assertSee('<input id="model" name="model" type="hidden" value="gemini-3-flash-preview">', escape: false);
});

it('does not render a standalone response container', function () {
    $this->get('/')->assertOk()->assertDontSee('<div id="response">', escape: false);
});

it('keeps streamed content from breaking out of the data island', function () {
    $content = (string) $this->withViewErrors([])->view('welcome', [
        'selectedModel' => 'gemini-3-flash-preview',
        'lastExchanges' => [
            'gemini-3-flash-preview' => [
                'prompt' => 'closing tag test',
                'response' => 'here you go </script><script>alert(1)</script>',
            ],
        ],
        'lastPrompt' => 'closing tag test',
        'lastResponse' => 'here you go </script><script>alert(1)</script>',
    ]);

    expect(welcomeData($content)['exchanges']['gemini-3-flash-preview']['response'])
        ->toBe('here you go </script><script>alert(1)</script>')
        ->and($content)->not->toContain('</script><script>alert(1)');
});

it('surfaces mid-stream errors and truncated streams', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // An error travels on the error event, so the pane has to read it.
    expect($script)->toContain("'recoverable' in event");

    // A connection that closes without a stream_end is a truncation, not a success.
    expect($script)->toContain('if (!streamFinished)');
    expect($script)->toContain('onTruncated(fullResponse)');
    expect($script)->toContain('The stream ended unexpectedly');
});

it('appends the failure notice instead of replacing the partial answer', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // The notice is its own element, placed after whatever streamed in.
    expect($script)->toContain("'data-stream-error': ''");

    // Writing the message over the pane would discard the half-generated answer.
    expect($script)->not->toContain('->text(message)');
});

it('hides the question and answer blocks when a model has no history', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // Both blocks are hidden when the model has nothing to show, so an empty
    // pane does not read as a broken one.
    expect($script)->toContain("(text ? '' : ' hidden')")
        ->and($script)->toContain("(exchange.response ? '' : ' hidden')");
});

it('reveals the hidden blocks when a new question is asked', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    expect($script)->toContain("\$pane.removeClass('hidden');")
        ->and($script)->toContain("\$pane.find('[data-question]').parent().removeClass('hidden');")
        ->and($script)->toContain("\$response.parent().removeClass('hidden');");

    // closest('div') from these inner nodes resolves to the text wrapper, not the
    // block that carries the hidden class, so the reveal would silently no-op.
    expect($script)->not->toContain("closest('div').removeClass('hidden')");
});

it('hides the whole pane when a model has neither a question nor an answer', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // Hiding only the inner blocks left the model and provider names floating in
    // an otherwise empty grid, so the pane itself is hidden while it is empty.
    expect($script)->toContain('const isEmpty = !exchange.prompt && !exchange.response;')
        ->and($script)->toContain("(isEmpty ? ' hidden' : '')");
});
