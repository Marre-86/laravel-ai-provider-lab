# Laravel AI Provider Lab

A study project for exploring the [Laravel AI SDK](https://laravel.com/ai) and integrating multiple LLM providers into a Laravel application.

## What it demonstrates

- Laravel AI SDK integration
- Multiple AI providers with a common interface
- Switching between providers from the UI
- Persistent conversations per provider
- Structured AI responses
- Conversation persistence using the Laravel AI conversation system
- Repository pattern for application-level conversation queries
- jQuery-powered provider switching
- Handling provider errors and unavailable models

## Providers

The project currently supports:

- Ollama
- Gemini
- OpenRouter

Additional providers can be added through the application configuration.

## Stack

- PHP
- Laravel
- Laravel AI SDK
- MySQL
- jQuery
- Ollama / Gemini / OpenRouter

## Purpose

This is primarily a learning project for understanding how Laravel's AI abstractions, service container, middleware, agents, structured responses, and persistent conversations work in a real Laravel application.