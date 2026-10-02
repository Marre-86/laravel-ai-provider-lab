<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Advisor;
use App\Ai\Agents\Reviewer;
use App\Models\User;
use App\Repositories\ConversationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;

class PromptController extends Controller
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
    ) {}

    public function index(): View
    {
        $user = User::find(1);

        $lastExchanges = collect(config('ai.selectable_providers'))
            ->mapWithKeys(fn (array $model, string $provider) => [
                $provider => $this->conversationRepository->latestExchange(
                    $user,
                    $provider,
                ),
            ]);

        $selectedProvider = session('provider', array_key_first(config('ai.selectable_providers')));

        return view('welcome', [
            'selectedProvider' => $selectedProvider,
            'lastExchanges' => $lastExchanges->toArray(),
            'lastPrompt' => session('prompt', $lastExchanges->get($selectedProvider)['prompt']),
            'lastResponse' => session('response', $lastExchanges->get($selectedProvider)['response']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.selectable_providers')))],
        ]);

        $selectedProvider = config("ai.selectable_providers.{$validated['provider']}");

        $user = User::find(1);

        $conversation = $this->conversationRepository->findOrCreate(
            $user,
            Advisor::class,
            $validated['provider'],
            $validated['prompt'],
        );

        session()->put('provider', $validated['provider']);

        try {
            $response = (new Advisor)
                ->continue($conversation->id, $user)
                ->prompt($validated['prompt'], provider: $selectedProvider['provider'], model: $selectedProvider['model']);
        } catch (ProviderOverloadedException|ProviderConnectionException|AiException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('Expected a structured response.');
        }

        return back()->with([
            'response' => $response['value'],
            'prompt' => $validated['prompt'],
        ]);
    }

    public function stream(Request $request): StreamableAgentResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'provider' => ['required', 'string'],
        ]);

        $user = User::find(1);

        $conversation = $this->conversationRepository->findOrCreate(
            $user,
            Advisor::class,
            $validated['provider'],
            $validated['prompt'],
        );

        $selectedProvider = config(
            "ai.selectable_providers.{$validated['provider']}"
        );

        return (new Advisor)
            ->continue($conversation->id, $user)
            ->stream(
                $validated['prompt'],
                provider: $selectedProvider['provider'],
                model: $selectedProvider['model'],
            );
    }

    public function review(Request $request): StreamableAgentResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'instruction' => ['required', 'string', 'max:10000'],
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.selectable_providers')))],
            'responses' => ['required', 'array'],
            'responses.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $submitted = collect(config('ai.selectable_providers'))
            ->map(fn (array $model, string $provider) => [
                'label' => $model['label'],
                'response' => $validated['responses'][$provider] ?? null,
            ])
            ->filter(fn (array $entry) => filled($entry['response']))
            ->map(fn (array $entry) => "### {$entry['label']}\n{$entry['response']}")
            ->implode("\n\n");

        if ($submitted === '') {
            throw ValidationException::withMessages([
                'responses' => 'There are no provider responses to evaluate.',
            ]);
        }

        $selectedProvider = config("ai.selectable_providers.{$validated['provider']}");

        return (new Reviewer)
            ->stream(
                $this->composeReviewPrompt($validated['prompt'], $validated['instruction'], $submitted),
                provider: $selectedProvider['provider'],
                model: $selectedProvider['model'],
            );
    }

    /**
     * Combine the original prompt, the evaluation instruction and the collected
     * provider answers into the single prompt sent to the reviewer.
     */
    private function composeReviewPrompt(string $prompt, string $instruction, string $submitted): string
    {
        return implode("\n\n", [
            "## Prompt given to each provider\n\n".$prompt,
            "## Instruction\n\n".$instruction,
            "## Answers\n\n".$submitted,
        ]);
    }
}
