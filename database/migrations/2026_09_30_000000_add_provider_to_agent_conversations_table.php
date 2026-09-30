<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ai.conversations.tables.conversations', 'agent_conversations'), function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('title')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('ai.conversations.tables.conversations', 'agent_conversations'), function (Blueprint $table): void {
            $table->dropColumn('provider');
        });
    }
};
