<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDonorActive;
use App\Http\Middleware\RequirePrivacyAcknowledgment;
use App\Services\AdminNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DonorVerificationUploadTest extends TestCase
{
    private string $jpeg;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['donor_verifications', 'donors', 'eligibility_status', 'donation_records', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('verification_status')->default('unverified');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('blood_type_id')->nullable();
        });
        Schema::create('donor_verifications', function (Blueprint $table): void {
            $table->increments('verification_id');
            $table->unsignedInteger('donor_id')->nullable();
            $table->string('document_type');
            $table->string('document_path');
            $table->string('document_back_path')->nullable();
            $table->string('document_preview_path')->nullable();
            $table->string('document_back_preview_path')->nullable();
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->unsignedInteger('donor_id');
            $table->string('status');
        });
        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->unsignedInteger('donor_id');
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->unsignedInteger('donor_id')->nullable();
            $table->text('message')->nullable();
            $table->string('notification_type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        DB::table('donors')->insert([
            'donor_id' => 716,
            'first_name' => 'Upload',
            'last_name' => 'Test',
            'verification_status' => 'rejected',
        ]);
        DB::table('eligibility_status')->insert([
            'donor_id' => 716,
            'status' => 'eligible',
        ]);

        $this->jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAiX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/An//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2Q==');
        Storage::fake('local');
        $adminNotifications = Mockery::mock(AdminNotificationService::class);
        $adminNotifications->shouldReceive('createAdminEvent')->once();
        $this->app->instance(AdminNotificationService::class, $adminNotifications);
    }

    public function test_web_upload_saves_original_and_optimized_preview_privately_with_authenticated_donor_id(): void
    {
        $this->withoutMiddleware([EnsureDonorActive::class, RequirePrivacyAcknowledgment::class]);

        $response = $this->withSession([
            'donor_id' => 716,
            'donor_name' => 'Upload Test',
        ])->post('/verification', [
            'document_type' => 'national_id',
            'document' => UploadedFile::fake()->createWithContent('original.jpg', $this->jpeg),
            'document_preview' => UploadedFile::fake()->createWithContent('preview.jpg', $this->jpeg),
        ]);

        $response->assertRedirect(route('donor.verification.index'));
        $this->assertDatabaseHas('donor_verifications', [
            'donor_id' => 716,
            'document_type' => 'national_id',
            'status' => 'pending',
        ]);
        $verification = DB::table('donor_verifications')->first();
        $this->assertNotNull($verification?->document_preview_path);
        $this->assertStringStartsWith('donor-verifications/716/', (string) $verification?->document_path);
        $this->assertStringStartsWith('donor-verifications/716/', (string) $verification?->document_preview_path);
        $this->assertSame($this->jpeg, Storage::disk('local')->get($verification->document_path));
        $this->assertTrue(Storage::disk('local')->exists($verification->document_preview_path));
        $this->assertDatabaseHas('donors', ['donor_id' => 716, 'verification_status' => 'pending']);
    }
}
