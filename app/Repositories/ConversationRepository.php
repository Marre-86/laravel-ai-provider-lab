<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

class ConversationRepository
{
    public function findFor(
        object $participant,
        string $agent,
        string $provider,
    ): ?Conversation {
        return Conversation::query()
            ->where('participant_type', Conversation::participantType($participant))
            ->where('participant_id', Conversation::participantKey($participant))
            // ->whereHas('messages', function ($query) use ($agent) {
            //     $query->where('agent', $agent);
            // })
            ->where('provider', $provider)
            ->latest('created_at')
            ->first();
    }

    public function createFor(
        object $participant,
        string $provider,
        string $title,
    ): Conversation {
        return Conversation::query()->create([
            'id' => (string) Str::uuid7(),
            'participant_type' => Conversation::participantType($participant),
            'participant_id' => Conversation::participantKey($participant),
            'title' => $title,
            'provider' => $provider,
        ]);
    }

    public function findOrCreate(
        object $participant,
        string $agent,
        string $provider,
        string $title,
    ): Conversation {
        return $this->findFor($participant, $agent, $provider)
            ?? $this->createFor($participant, $provider, $title);
    }

    public function latestExchange(
        User $user,
        string $provider,
    ): array {
        $conversation = Conversation::query()
            ->where('participant_type', User::class)
            ->where('participant_id', $user->id)
            ->where('provider', $provider)
            ->latest('updated_at')
            ->first();

        $messages = $conversation?->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->limit(2)
            ->get()
            ->keyBy('role') ?? collect();

        return [
            'prompt' => $messages->get('user')?->content,
            'response' => $messages->get('assistant')?->content,
        ];
    }
}
