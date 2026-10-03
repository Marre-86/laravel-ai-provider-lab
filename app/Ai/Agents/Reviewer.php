<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Evaluates the answers the selectable models gave to one prompt.
 *
 * Deliberately stateless: it must not implement Conversational, because the
 * evaluation should not read or extend the conversation the panes display.
 */
class Reviewer implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You compare answers produced by several AI models for the same prompt, then evaluate them against that prompt.';
    }
}
