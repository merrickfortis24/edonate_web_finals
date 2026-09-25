<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (! Schema::hasColumn('posts', 'is_donation')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->boolean('is_donation')->default(false)->after('type');
            });
        }

        // Existing event posts in this feed represent blood-donation events.
        DB::table('posts')->where('type', 'event')->update(['is_donation' => true]);

        // Remove only the obsolete inline booking URL from existing event
        // posts; keep the rest of each post's content and history intact.
        DB::table('posts')
            ->where('type', 'event')
            ->where('content', 'like', '%Book appointment:%')
            ->orderBy('id')
            ->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    $content = (string) $post->content;
                    $updatedContent = preg_replace(
                        '/^[ \t]*Book appointment:[^\r\n]*(?:\r\n|\r|\n|$)/mi',
                        '',
                        $content
                    );

                    if ($updatedContent !== null && $updatedContent !== $content) {
                        DB::table('posts')->where('id', $post->id)->update([
                            'content' => $updatedContent,
                        ]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        // Preserve the classification and cleaned post text on rollback.
    }
};
