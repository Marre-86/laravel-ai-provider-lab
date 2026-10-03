<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/welcome.js'])
        @endif
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto flex min-h-screen w-full max-w-8xl items-center px-6 py-12">
            <section class="w-full rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                <div class="mx-auto w-full max-w-[80vw] px-4">
                    <div class="grid gap-6 md:grid-cols-2 grid-rows-1">
                        <div id="prompt-panel">
                            @csrf
                            <input id="model" name="model" type="hidden" value="{{ old('model', $selectedModel) }}">
                            <div>
                                {{-- <label for="prompt" class="block text-sm font-medium">Prompt</label> --}}
                                <textarea id="prompt" name="prompt" rows="7" required maxlength="10000" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Write your prompt here...">{{ old('prompt') }}</textarea>
                                @error('prompt')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            {{-- The popup is anchored to this row, not the icon, so it cannot overflow narrow screens. --}}
                            <div class="mt-2 flex items-center gap-3">
                                <button id="submit-button" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <svg id="submit-spinner" class="hidden size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 018 4h-4a4 4 0 00-4-4H4z"></path>
                                    </svg>
                                    <span id="submit-label">Ask LLM</span>
                                </button>
                                <div class="group relative">
                                    <button type="button" aria-label="How this works" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-gray-300 text-xs font-semibold text-gray-600 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                        ?
                                    </button>
                                    <div class="invisible absolute bottom-full left-0 z-20 mb-2 w-72 rounded-lg bg-gray-50 p-5 opacity-0 shadow-lg ring-1 ring-gray-200 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                        <h2 class="text-sm font-semibold text-gray-700">How this works</h2>
                                        <p class="mt-2 text-sm text-gray-800">
                                            Your prompt is sent to every selected model at the same time. Each answer streams into its own pane below as the text arrives, so a slow model does not hold up the others.
                                        </p>
                                        <p class="mt-2 text-sm text-gray-800">
                                            If a model returns an error part way through, the answer is kept and the failure is flagged underneath it. Switch models in <span class="font-mono text-xs">config/ai.php</span>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @if (session('error'))
                                <div id="connection-error" class="text-red-600">
                                    {{ session('error') }}
                                </div>
                            @endif
                        </div>
                        {{-- Hidden until the models have finished answering: there is nothing to evaluate before then. --}}
                        <div id="review-panel" class="hidden rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200 md:col-start-2">
                            <h2 class="text-sm font-semibold text-gray-700">Evaluate responses</h2>
                            <p class="mt-2 text-sm text-gray-800">
                                Sends the prompt and every answer collected below to one model and shows what it makes of them.
                            </p>

                            <label for="review-model" class="mt-4 block text-sm font-medium text-gray-700">Review with</label>
                            <select id="review-model" name="model" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                                @foreach (config('ai.selectable_models') as $key => $model)
                                    <option value="{{ $key }}">{{ $model['label'] }} &mdash; {{ $model['model'] }}</option>
                                @endforeach
                            </select>

                            <label for="review-instruction" class="mt-4 block text-sm font-medium text-gray-700">Instruction</label>
                            <textarea id="review-instruction" name="instruction" rows="5" maxlength="10000" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">{{ old('instruction', config('ai.evaluation_prompt')) }}</textarea>

                            <button id="review-button" type="button" class="mt-2 inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <svg id="review-spinner" class="hidden size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 018 4h-4a4 4 0 00-4-4H4z"></path>
                                </svg>
                                <span id="review-label">Evaluate responses</span>
                            </button>

                            {{-- Stays collapsed until Evaluate responses is pressed, so the block does not sit there empty. --}}
                            <div id="review-output" class="mt-4 hidden rounded-lg bg-white p-5 ring-1 ring-gray-200" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
                <div id="exchange-container" class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4"></div>
            </section>
        </main>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        @php
            $welcomeData = [
                'exchanges' => $lastExchanges,
                'models' => config('ai.selectable_models'),
                'streamUrl' => route('prompts.stream'),
                'reviewUrl' => route('prompts.review'),
            ];
        @endphp
        <script type="application/json" id="welcome-data">@json($welcomeData)</script>
    </body>
</html>
