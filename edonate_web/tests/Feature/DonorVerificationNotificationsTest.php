<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use App\Services\AdminNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DonorVerificationNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['admin_notification_preferences', 'admin_notifications', 'notifications', 'donor_verifications', 'donors'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('verification_status')->nullable();
        });

        Schema::create('donor_verifications', function (Blueprint $table): void {
            $table->increments('verification_id');
            $table->unsignedInteger('donor_id');
            $table->string('document_type');
            $table->string('document_path');
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->unsignedInteger('donor_id')->nullable();
            $table->text('message')->nullable();
            $table->string('notification_type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->bigIncrements('admin_notification_id');
            $table->string('title');
            $table->text('message');
            $table->string('notification_type')->default('system');
            $table->string('channel')->nullable()->default('system');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_approving_donor_verification_creates_both_notification_inboxes(): void
    {
        DB::table('donors')->insert([
            'donor_id' => 1,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'verification_status' => 'pending',
        ]);

        DB::table('donor_verifications')->insert([
            'verification_id' => 1,
            'donor_id' => 1,
            'document_type' => 'government_id',
            'document_path' => 'donor-verifications/1/demo.jpg',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class])
            ->withSession([
                'admin_id' => 7,
                'admin_role' => 'admin',
                'admin_username' => 'test-admin',
                'admin_full_name' => 'Test Admin',
            ])
            ->patch('/admin/donor-verifications/1/approve');

        $response->assertRedirect(route('admin.donor-verifications.index'));
        $this->assertDatabaseHas('donors', [
            'donor_id' => 1,
            'verification_status' => 'verified',
        ]);
        $this->assertDatabaseHas('donor_verifications', [
            'verification_id' => 1,
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('notifications', [
            'donor_id' => 1,
            'notification_type' => 'donor_verification_approved',
            'is_read' => 0,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'notification_type' => 'donor_verification_approved',
            'related_type' => 'donor_verification',
            'related_id' => 1,
            'is_read' => 0,
        ]);

        $this->assertSame(1, app(AdminNotificationService::class)->unreadCount());

        $navbar = view('adminlte::partials.navbar')->render();
        $this->assertStringContainsString('admin-navbar-notification-badge', $navbar);
        $this->assertStringContainsString('>1<', $navbar);
        $this->assertStringContainsString('1 unread notifications', $navbar);
    }

    public function test_donor_and_admin_notification_banners_render_navigation_links(): void
    {
        $donorBanner = view('components.dashboard.notification-banner', [
            'notification' => (object) [
                'notification_type' => 'donor_verification_approved',
                'message' => 'Your identity verification has been approved.',
                'created_at' => now(),
            ],
        ])->render();

        $this->assertStringContainsString('New notification', $donorBanner);
        $this->assertStringContainsString('Your identity verification has been approved.', $donorBanner);
        $this->assertStringContainsString(route('donor.alerts'), $donorBanner);

        $adminBanner = view('components.admin-notification-banner', [
            'notification' => [
                'title' => 'Donor Verification Approved',
                'message' => "Juan Dela Cruz's identity verification was approved.",
                'url' => route('admin.notification-center'),
            ],
        ])->render();

        $this->assertStringContainsString('Donor Verification Approved', $adminBanner);
        $this->assertStringContainsString(route('admin.notification-center'), $adminBanner);
    }
}
