# Laravel AI Model Lab

A study project for exploring the [Laravel AI SDK](https://laravel.com/ai) and integrating multiple LLMs into a Laravel application.

## What it demonstrates

- Laravel AI SDK integration
- Multiple models behind one interface
- Concurrent streaming responses
- Persistent conversations, kept separately per model
- Structured AI responses

## Models

The page compares the models listed in `config/ai.php` under `selectable_models`.
Each entry's key is the model's own name and identifies one selectable choice:

| Key | Label | Driver | Model id |
|---|---|---|---|
| `gemma3-12b` | Gemma 3 12B | `ollama` | `gemma3:12b` |
| `gemini-3-flash-preview` | Gemini 3 Flash | `gemini` | `gemini-3-flash-preview` |
| `nemotron-3-ultra` | Nemotron 3 Ultra | `openrouter` | `nvidia/nemotron-3-ultra-550b-a55b:free` |
| `space-bunny-alpha` | Space Bunny Alpha | `openrouter` | `stealth/space-bunny-alpha` |

Entries can be commented out to switch a model off locally; the page renders
whatever is left, including none at all. Only the models that are active get a pane.

Additional models can be added by adding another entry to that array; the panes,
the streaming requests and the review dropdown all follow the configuration.

The key names a *model*, not a *provider*, which is why two entries can share one
driver: `nemotron-3-ultra` and `space-bunny-alpha` both call OpenRouter and differ
only in the model they request. Keys are also what get stored in a conversation's
`model` column, so renaming one orphans that model's saved history.

### Space Bunny Alpha

`space-bunny-alpha` is served by OpenRouter under the model id
`stealth/space-bunny-alpha`, so it reuses the existing `OPENROUTER_API_KEY` rather
than needing a key of its own. The model is free during its preview ($0 in / $0 out),
which makes it a genuinely free comparison target next to the paid Gemini calls.

The free window is temporary and the model is a "stealth" release with no published
end date, so keep the key in configuration rather than scattering it through the code.

## Streaming

The application can send the same prompt to every selected model concurrently.

Each model is handled by a separate browser `fetch()` request, allowing responses to stream independently.

## Stack

- PHP 8.3+
- Laravel 13
- Laravel AI SDK
- MySQL
- jQuery
- Ollama / Gemini / OpenRouter

## Development

The project uses Laravel's `composer run dev` workflow with the Laravel development TUI.

For local development, the application uses multiple PHP built-in server workers so concurrent streaming requests can be processed in parallel.

### Setup

```sh
composer install
php artisan migrate:fresh --seed
npm install && npm run build
```

The `--seed` is required, not optional. There is no authentication: `PromptController`
reads `User::find(1)` as its conversation participant, so that row has to exist or the
index page returns a 500. The seeder is idempotent, so running it repeatedly is safe.


## Response Evaluation

You can send the prompt and every answer collected below to one of the configured models for evaluation.

The evaluation textarea is pre-populated from `config('ai.evaluation_prompt')` and can be edited before submission.

## Purpose

This is primarily a learning project for understanding how Laravel's AI abstractions, service container, middleware, agents, streaming, structured responses, and persistent conversations work in a real Laravel application.