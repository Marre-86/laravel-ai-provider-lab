<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Advisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
        ]);

        $response = (new Advisor)
            ->prompt($validated['prompt']);

        return back()->with('response', $response['value']);
    }
}
