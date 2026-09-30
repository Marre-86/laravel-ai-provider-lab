<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Advisor;
use App\Models\User;
use App\Repositories\ConversationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
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
}
