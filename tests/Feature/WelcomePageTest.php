<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['id' => 1]);
});

function welcomeData(string $content): array
{
    preg_match('/<script type="application\/json" id="welcome-data">(.*?)<\/script>/s', $content, $matches);

    return json_decode($matches[1], true);
}

it('passes every selectable provider and its model to the response panes', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect(welcomeData($content)['providers'])->toBe(config('ai.selectable_providers'));
});

it('passes the stream endpoint and a pane entry per selectable provider to the panes', function () {
    $content = $this->get('/')->assertOk()->getContent();

    $data = welcomeData($content);

    expect($data['streamUrl'])->toBe(route('prompts.stream'))
        ->and(array_keys($data['exchanges']))->toBe(array_keys(config('ai.selectable_providers')))
        ->and($data['exchanges']['gemini'])->toBe(['prompt' => null, 'response' => null]);
});

it('renders the panes container as a grid and no longer renders provider buttons', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="exchange-container" class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3"', escape: false)
        ->assertDontSee('provider-button', escape: false);
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

it('keeps streamed content from breaking out of the data island', function () {
    $content = (string) $this->withViewErrors([])->view('welcome', [
        'selectedProvider' => 'gemini',
        'lastExchanges' => [
            'gemini' => [
                'prompt' => 'closing tag test',
                'response' => 'here you go </script><script>alert(1)</script>',
            ],
        ],
        'lastPrompt' => 'closing tag test',
        'lastResponse' => 'here you go </script><script>alert(1)</script>',
    ]);

    expect(welcomeData($content)['exchanges']['gemini']['response'])
        ->toBe('here you go </script><script>alert(1)</script>')
        ->and($content)->not->toContain('</script><script>alert(1)');
});

it('surfaces mid-stream provider errors and truncated streams', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // A provider error travels on the error event, so the pane has to read it.
    expect($script)->toContain("'recoverable' in event");

    // A connection that closes without a stream_end is a truncation, not a success.
    expect($script)->toContain('if (! streamFinished)');
    expect($script)->toContain('The stream ended unexpectedly');
});

it('appends the failure notice instead of replacing the partial answer', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    // The notice is its own element, placed after whatever streamed in.
    expect($script)->toContain("'data-stream-error': ''");

    // Writing the message over the pane would discard the half-generated answer.
    expect($script)->not->toContain('->text(message)');
});
