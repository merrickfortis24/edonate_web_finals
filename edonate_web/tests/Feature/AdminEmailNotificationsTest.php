<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use App\Mail\AdminNotificationMail;
use App\Services\AdminNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminEmailNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('admin_notification_preferences');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_notification_preferences', function (Blueprint $table): void {
            $table->bigIncrements('admin_notification_preference_id');
            $table->unsignedInteger('admin_id');
            $table->boolean('email_enabled')->default(false);
            $table->timestamps();
            $table->unique('admin_id');
        });

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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('admin_notification_preferences');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_admin_email_preference_is_saved_and_loaded_by_settings(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'full_name' => 'Notification Admin',
            'username' => 'notification-admin',
            'email' => 'notification-admin@example.test',
            'role' => 'admin',
            'created_at' => now(),
        ]);

        $this->authenticateAsAdmin((int) $adminId);

        $response = $this->postJson('/admin/settings/notifications', [
            'email_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('notifications.email', true)
            ->assertJsonPath('notifications.emailAddress', 'notification-admin@example.test');

        $this->assertDatabaseHas('admin_notification_preferences', [
            'admin_id' => $adminId,
            'email_enabled' => 1,
        ]);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('notification-admin@example.test');
    }

    public function test_admin_event_email_is_sent_only_to_enabled_admins(): void
    {
        Mail::fake();

        $enabledAdminId = DB::table('admins')->insertGetId([
            'full_name' => 'Enabled Admin',
            'username' => 'enabled-admin',
            'email' => 'enabled-admin@example.test',
            'role' => 'admin',
            'created_at' => now(),
        ]);

        $disabledAdminId = DB::table('admins')->insertGetId([
            'full_name' => 'Disabled Admin',
            'username' => 'disabled-admin',
            'email' => 'disabled-admin@example.test',
            'role' => 'admin',
            'created_at' => now(),
        ]);

        DB::table('admin_notification_preferences')->insert([
            [
                'admin_id' => $enabledAdminId,
                'email_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => $disabledAdminId,
                'email_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(AdminNotificationService::class)->createAdminEvent(
            'report',
            'Demo Report Ready',
            'The monthly demo report is ready for review.',
            'report',
            42
        );

        Mail::assertSent(AdminNotificationMail::class, 1);
        Mail::assertSent(AdminNotificationMail::class, function (AdminNotificationMail $mail): bool {
            return $mail->hasTo('enabled-admin@example.test')
                && ! $mail->hasTo('disabled-admin@example.test');
        });
    }

    private function authenticateAsAdmin(int $adminId): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            EnsureAdminRole::class,
        ]);

        $this->withSession([
            'admin_id' => $adminId,
            'admin_role' => 'admin',
            'admin_username' => 'notification-admin',
            'admin_full_name' => 'Notification Admin',
        ]);
    }
}
