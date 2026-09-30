<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-6 py-12">
            <section class="w-full rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                {{-- <h1 class="text-2xl font-semibold">Submit a prompt</h1>
                <p class="mt-2 text-sm text-gray-600">Enter a prompt below to send it to the application.</p> --}}
                <form action="{{ route('prompts.store') }}" method="POST" class="mt-6 space-y-4">
                    @csrf
                    <input id="provider" name="provider" type="hidden" value="{{ old('provider', $selectedProvider) }}">
                    <div>
                        <span class="block text-sm font-medium">Provider</span>
                        <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Choose a model">
                            @foreach (config('ai.selectable_providers') as $key => $provider)
                                <button type="button" class="provider-button rounded-md border px-4 py-2 text-sm font-medium transition" data-provider="{{ $key }}">
                                    {{ $provider['label'] }}
                                </button>
                            @endforeach
                        </div>
                        @error('model')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        {{-- <label for="prompt" class="block text-sm font-medium">Prompt</label> --}}
                        <textarea id="prompt" name="prompt" rows="7" required maxlength="10000" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Write your prompt here...">{{ old('prompt') }}</textarea>
                        @error('prompt')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button id="submit-button" type="submit" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg id="submit-spinner" class="hidden size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span id="submit-label">Ask LLM</span>
                    </button>
                    @if (session('error'))
                        <div id="connection-error" class="text-red-600">
                            {{ session('error') }}
                        </div>
                    @endif
                </form>
                <div id="exchange-container"></div>
            </section>
        </main>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#prompt').focus();
                $('.provider-button').each(function() {
                    var $btn = $(this);
                    var isCurrent = $btn.data('provider') === $('#provider').val();
                    $btn.toggleClass('active', isCurrent);
                });
                renderExchange($('#provider').val());
            });
            const exchanges = @json($lastExchanges);
            function renderExchange(provider) {
                const exchange = exchanges[provider];
                console.log(exchange)
                $('#exchange-container').empty();
                $('#connection-error').empty();
                $('#prompt').focus();
                if (!exchange || (!exchange.prompt && !exchange.response)) return;
                $('<div>', { class: 'exchange mt-8 rounded-lg bg-indigo-50 p-5 ring-1 ring-indigo-200' })
                    .append($('<h2>', { class: 'text-sm font-semibold text-indigo-900', text: 'Last question' }))
                    .append($('<p>', { class: 'mt-2 whitespace-pre-wrap text-indigo-950', text: exchange.prompt || '' }))
                    .appendTo('#exchange-container');
                $('<div>', { class: 'exchange mt-8 rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200', role: 'status' })
                    .append($('<h2>', { class: 'text-sm font-semibold text-gray-700', text: ({{ Js::from(config('ai.selectable_providers')) }}[provider]?.label || 'Response') + ' response' }))
                    .append($('<p>', { class: 'mt-2 whitespace-pre-wrap text-gray-800', text: exchange.response || '' }))
                    .appendTo('#exchange-container');
            }
            $('.provider-button').on('click', function() {
                var $btn = $(this);
                var provider = $btn.data('provider');

                $('#provider').val(provider);

                $('.provider-button').removeClass('active');   // убираем active у всех
                $btn.addClass('active');                       // ставим у нажатой

                renderExchange(provider);
            });
            $('form').on('submit', function () {
                $('#submit-button').prop('disabled', true);
                $('#submit-spinner').removeClass('hidden');
                $('#submit-label').text('Generating...');
            });
            $('#prompt').on('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    $(this).closest('form').trigger('submit');
                }
            });
        </script>
        <style>
            .provider-button {
                transition: all 0.2s ease;
                /* базовые нейтральные стили */
                background-color: #ffffff;
                border-color: #d1d5db;
                color: #374151;
            }

            /* hover ТОЛЬКО для НЕактивных */
            .provider-button:not(.active):hover {
                background-color: #f3f4f6;
                border-color: #9ca3af;
                color: #111827;
                cursor: pointer;
            }

            /* активная кнопка — фиксированный стиль, без hover */
            .provider-button.active {
                background-color: #4f46e5;
                border-color: #4f46e5;
                color: #ffffff;
            }

            .provider-button.active:hover {
                /* явно запрещаем любые изменения при наведении */
                background-color: #4f46e5 !important;
                border-color: #4f46e5 !important;
                color: #ffffff !important;
                cursor: default;
            }

        </style>

    </body>
</html>
