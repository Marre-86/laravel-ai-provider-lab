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
                <h1 class="text-2xl font-semibold">Submit a prompt</h1>
                <p class="mt-2 text-sm text-gray-600">Enter a prompt below to send it to the application.</p>
                <form action="{{ route('prompts.store') }}" method="POST" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="prompt" class="block text-sm font-medium">Prompt</label>
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
                        <span id="submit-label">Submit</span>
                    </button>
                </form>
                @if (session('response'))
                    <div class="mt-8 rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200" role="status" aria-live="polite">
                        <h2 class="text-sm font-semibold text-gray-700">Response</h2>
                        <p class="mt-2 whitespace-pre-wrap text-gray-800">{{ session('response') }}</p>
                    </div>
                @endif
            </section>
        </main>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#prompt').focus();
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
    </body>
</html>
