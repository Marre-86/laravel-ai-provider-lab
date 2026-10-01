# Laravel AI Provider Lab

A study project for exploring the [Laravel AI SDK](https://laravel.com/ai) and integrating multiple LLM providers into a Laravel application.

## What it demonstrates

- Laravel AI SDK integration
- Multiple providers with a common interface
- Concurrent streaming responses
- Persistent conversations
- Structured AI responses

## Providers

The project currently supports:

- Ollama
- Gemini
- OpenRouter

Additional providers can be added through the application configuration.

## Streaming

The application can send the same prompt to multiple providers concurrently.

Each provider is handled by a separate browser `fetch()` request, allowing responses to stream independently.

## Stack

- PHP
- Laravel
- Laravel AI SDK
- MySQL
- jQuery
- Ollama / Gemini / OpenRouter

## Development

The project uses Laravel's `composer run dev` workflow with the Laravel development TUI.

For local development, the application uses multiple PHP built-in server workers so concurrent streaming requests can be processed in parallel.

## Purpose

This is primarily a learning project for understanding how Laravel's AI abstractions, service container, middleware, agents, streaming, structured responses, and persistent conversations work in a real Laravel application.