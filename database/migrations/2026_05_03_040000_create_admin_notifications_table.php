<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table): void {
                $table->bigIncrements('admin_notification_id');
                $table->string('title', 150);
                $table->text('message');
                $table->string('notification_type', 50)->default('system');
                $table->string('channel', 30)->nullable()->default('system');
                $table->string('related_type', 100)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            return;
        }

        Schema::table('admin_notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_notifications', 'title')) {
                $table->string('title', 150)->nullable()->after('admin_notification_id');
            }

            if (! Schema::hasColumn('admin_notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }

            if (! Schema::hasColumn('admin_notifications', 'notification_type')) {
                $table->string('notification_type', 50)->default('system')->after('message');
            }

            if (! Schema::hasColumn('admin_notifications', 'channel')) {
                $table->string('channel', 30)->nullable()->default('system')->after('notification_type');
            }

            if (! Schema::hasColumn('admin_notifications', 'related_type')) {
                $table->string('related_type', 100)->nullable()->after('channel');
            }

            if (! Schema::hasColumn('admin_notifications', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }

            if (! Schema::hasColumn('admin_notifications', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('related_id');
            }

            if (! Schema::hasColumn('admin_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }

            if (! Schema::hasColumn('admin_notifications', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('read_at');
            }

            if (! Schema::hasColumn('admin_notifications', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            if (! Schema::hasColumn('admin_notifications', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
