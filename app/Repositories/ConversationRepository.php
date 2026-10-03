<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

class ConversationRepository
{
    public function findFor(
        object $participant,
        string $model,
    ): ?Conversation {
        return Conversation::query()
            ->where('participant_type', Conversation::participantType($participant))
            ->where('participant_id', Conversation::participantKey($participant))
            ->where('model', $model)
            ->latest('created_at')
            ->first();
    }

    public function createFor(
        object $participant,
        string $model,
        string $title,
    ): Conversation {
        return Conversation::query()->create([
            'id' => (string) Str::uuid7(),
            'participant_type' => Conversation::participantType($participant),
            'participant_id' => Conversation::participantKey($participant),
            'title' => $title,
            'model' => $model,
        ]);
    }

    public function findOrCreate(
        object $participant,
        string $model,
        string $title,
    ): Conversation {
        return $this->findFor($participant, $model)
            ?? $this->createFor($participant, $model, $title);
    }

    public function latestExchange(
        User $user,
        string $model,
    ): array {
        $conversation = Conversation::query()
            ->where('participant_type', User::class)
            ->where('participant_id', $user->id)
            ->where('model', $model)
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
