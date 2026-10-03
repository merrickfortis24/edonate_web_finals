<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDonorVerificationDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('donor_verifications');
        Schema::dropIfExists('donors');
        Schema::dropIfExists('admins');
        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('contact_number')->nullable();
            $table->string('verification_status')->nullable();
        });
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->nullable();
        });
        Schema::create('donor_verifications', function (Blueprint $table): void {
            $table->increments('verification_id');
            $table->unsignedInteger('donor_id');
            $table->string('document_type');
            $table->string('document_path');
            $table->string('document_back_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::table('donors')->insert([
            'donor_id' => 716,
            'first_name' => 'Mobile',
            'last_name' => 'Donor',
            'contact_number' => '09170000000',
            'verification_status' => 'pending',
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('uploads/verification/front.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        Storage::disk('local')->put('uploads/verification/back.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        DB::table('donor_verifications')->insert([
            'verification_id' => 1,
            'donor_id' => 716,
            'document_type' => 'national_id',
            'document_path' => 'uploads/verification/front.png',
            'document_back_path' => 'uploads/verification/back.png',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_fetch_both_front_and_back_document_images(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $front = $this->get('/admin/donor-verifications/1/document?side=front');
        $front->assertOk();
        $front->assertHeader('Content-Type', 'image/png');
        $front->assertHeader('Content-Disposition', 'inline; filename="donor-verification-1-front.png"');
        $front->assertHeader('Cache-Control', 'no-store, private');

        $back = $this->get('/admin/donor-verifications/1/document?side=back');
        $back->assertOk();
        $back->assertHeader('Content-Type', 'image/png');
        $back->assertHeader('Content-Disposition', 'inline; filename="donor-verification-1-back.png"');
    }

    public function test_back_document_request_returns_not_found_when_not_submitted(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        DB::table('donor_verifications')->where('verification_id', 1)->update(['document_back_path' => null]);

        $this->get('/admin/donor-verifications/1/document?side=back')->assertNotFound();
    }

    public function test_document_side_must_be_front_or_back(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->get('/admin/donor-verifications/1/document?side=original')->assertNotFound();
    }

    public function test_view_document_modal_includes_both_submitted_id_sides(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->get('/admin/donor-verifications');

        $response->assertOk();
        $response->assertSee('Front of ID');
        $response->assertSee('Back of ID');
        $response->assertSee(route('admin.donor-verifications.document', ['verification' => 1, 'side' => 'front']));
        $response->assertSee(route('admin.donor-verifications.document', ['verification' => 1, 'side' => 'back']));
    }
}
