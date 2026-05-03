<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->integer('donor_id')->nullable()->index();
                $table->string('title', 150)->nullable();
                $table->text('message')->nullable();
                $table->string('notification_type', 50)->nullable();
                $table->string('channel', 30)->nullable();
                $table->string('recipient_type', 50)->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('related_type', 100)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });

            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title', 150)->nullable()->after('donor_id');
            }

            if (! Schema::hasColumn('notifications', 'channel')) {
                $table->string('channel', 30)->nullable()->after('notification_type');
            }

            if (! Schema::hasColumn('notifications', 'recipient_type')) {
                $table->string('recipient_type', 50)->nullable()->after('channel');
            }

            if (! Schema::hasColumn('notifications', 'recipient_id')) {
                $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');
            }

            if (! Schema::hasColumn('notifications', 'related_type')) {
                $table->string('related_type', 100)->nullable()->after('recipient_id');
            }

            if (! Schema::hasColumn('notifications', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }

            if (! Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }

            if (! Schema::hasColumn('notifications', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            if (! Schema::hasColumn('notifications', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
        });

        if (Schema::hasColumn('notifications', 'read_at') && Schema::hasColumn('notifications', 'is_read')) {
            DB::table('notifications')
                ->where('is_read', 1)
                ->whereNull('read_at')
                ->update(['read_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            foreach ([
                'title',
                'channel',
                'recipient_type',
                'recipient_id',
                'related_type',
                'related_id',
                'read_at',
                'updated_at',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
