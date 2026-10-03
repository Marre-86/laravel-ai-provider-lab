<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The lab has no authentication: PromptController reads User::find(1) as its
     * participant. Seeding that row with an explicit id keeps a freshly wiped
     * database usable after `migrate --seed`, instead of every page erroring with
     * a null participant.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Lab User',
                'email' => 'lab@example.com',
                'password' => 'password',
            ],
        );
    }
}
