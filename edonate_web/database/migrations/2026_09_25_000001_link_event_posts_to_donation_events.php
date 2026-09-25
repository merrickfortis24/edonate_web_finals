<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'posts_event_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (! Schema::hasColumn('posts', 'event_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                // donation_events.event_id is a signed INT in the existing
                // Hostinger schema, so keep the relationship key compatible.
                $table->integer('event_id')->nullable()->after('event_location');
            });
        }

        if (! $this->hasIndex('posts', self::INDEX)) {
            Schema::table('posts', function (Blueprint $table): void {
                // Multiple legacy/non-event posts may keep NULL; each linked
                // donation event can have only one feed post.
                $table->unique('event_id', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        // Keep the nullable links on rollback; removing them would orphan
        // already-published event posts and discard relationship history.
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
