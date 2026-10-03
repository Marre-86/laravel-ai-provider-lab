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

        $lastExchanges = collect(config('ai.selectable_models'))
            ->mapWithKeys(fn (array $model, string $key) => [
                $key => $this->conversationRepository->latestExchange(
                    $user,
                    $key,
                ),
            ]);

        $selectedModel = session('model', array_key_first(config('ai.selectable_models')));

        // With every model switched off there is no selected entry to read, and
        // $lastExchanges->get(null) would index into null.
        $selected = $lastExchanges->get($selectedModel, ['prompt' => null, 'response' => null]);

        return view('welcome', [
            'selectedModel' => $selectedModel,
            'lastExchanges' => $lastExchanges->toArray(),
            'lastPrompt' => session('prompt', $selected['prompt']),
            'lastResponse' => session('response', $selected['response']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'model' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.selectable_models')))],
        ]);

        $selectedModel = config("ai.selectable_models.{$validated['model']}");

        $user = User::find(1);

        $conversation = $this->conversationRepository->findOrCreate(
            $user,
            $validated['model'],
            $validated['prompt'],
        );

        session()->put('model', $validated['model']);

        try {
            $response = (new Advisor)
                ->continue($conversation->id, $user)
                ->prompt($validated['prompt'], provider: $selectedModel['provider'], model: $selectedModel['model']);
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
            // Without the in: rule an unknown key resolves to null below and the
            // request fails with a 500 instead of a validation error.
            'model' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.selectable_models')))],
        ]);

        $user = User::find(1);

        $conversation = $this->conversationRepository->findOrCreate(
            $user,
            $validated['model'],
            $validated['prompt'],
        );

        $selectedModel = config(
            "ai.selectable_models.{$validated['model']}"
        );

        return (new Advisor)
            ->continue($conversation->id, $user)
            ->stream(
                $validated['prompt'],
                provider: $selectedModel['provider'],
                model: $selectedModel['model'],
            );
    }

    public function review(Request $request): StreamableAgentResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'instruction' => ['required', 'string', 'max:10000'],
            'model' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.selectable_models')))],
            'responses' => ['required', 'array'],
            'responses.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $submitted = collect(config('ai.selectable_models'))
            ->map(fn (array $model, string $key) => [
                'label' => $model['label'],
                'response' => $validated['responses'][$key] ?? null,
            ])
            ->filter(fn (array $entry) => filled($entry['response']))
            ->map(fn (array $entry) => "### {$entry['label']}\n{$entry['response']}")
            ->implode("\n\n");

        if ($submitted === '') {
            throw ValidationException::withMessages([
                'responses' => 'There are no model responses to evaluate.',
            ]);
        }

        $selectedModel = config("ai.selectable_models.{$validated['model']}");

        return (new Reviewer)
            ->stream(
                $this->composeReviewPrompt($validated['prompt'], $validated['instruction'], $submitted),
                provider: $selectedModel['provider'],
                model: $selectedModel['model'],
            );
    }

    /**
     * Combine the original prompt, the evaluation instruction and the collected
     * model answers into the single prompt sent to the reviewer.
     */
    private function composeReviewPrompt(string $prompt, string $instruction, string $submitted): string
    {
        return implode("\n\n", [
            "## Prompt given to each model\n\n".$prompt,
            "## Instruction\n\n".$instruction,
            "## Answers\n\n".$submitted,
        ]);
    }
}
