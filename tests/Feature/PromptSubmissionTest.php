<?php

use App\Ai\Agents\Advisor;

use function Pest\Laravel\post;

it('displays the generated response', function () {
    Advisor::fake([['value' => 'Laravel is a PHP framework.']]);

    post(route('prompts.store'), ['prompt' => 'Explain Laravel routing'])
        ->assertRedirect('/')
        ->assertSessionHas('response', 'Laravel is a PHP framework.');
});

it('requires a prompt', function () {
    post(route('prompts.store'), ['prompt' => ''])->assertSessionHasErrors('prompt');
});
