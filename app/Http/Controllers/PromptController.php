<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Advisor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
        ]);

        $user = User::find(1);

        $response = (new Advisor)
            ->continueLastConversation($user)
            ->prompt($validated['prompt']);

        return back()->with('response', $response['value']);
    }
}
