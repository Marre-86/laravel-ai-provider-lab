<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the participant the lab reads as user one', function () {
    expect(User::query()->count())->toBe(0);

    $this->seed(DatabaseSeeder::class);

    // PromptController reads User::find(1) as its participant, so that exact row
    // has to exist or the index page errors on a freshly seeded database.
    expect(User::find(1))->not->toBeNull()
        ->and(User::query()->count())->toBe(1);
});

it('seeds the same user once when run repeatedly', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);
});

it('renders the index page once the lab user is seeded', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get('/')->assertOk();
});
