<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            $table->unsignedInteger('donor_id')->nullable();
            $table->string('document_type');
            $table->string('document_path');
            $table->string('document_back_path')->nullable();
            $table->string('document_preview_path')->nullable();
            $table->string('document_back_preview_path')->nullable();
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

    public function test_admin_can_fetch_private_optimized_preview_without_caching_it_in_browser(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        Storage::disk('local')->put('uploads/verification/front-preview.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        DB::table('donor_verifications')->where('verification_id', 1)->update([
            'document_preview_path' => 'uploads/verification/front-preview.png',
        ]);

        $preview = $this->get('/admin/donor-verifications/1/document?side=front&rendition=preview');
        $preview->assertOk();
        $preview->assertHeader('Content-Type', 'image/png');
        $preview->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_list_keeps_unlinked_submissions_visible_and_labels_missing_donor(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        DB::table('donor_verifications')->insert([
            'verification_id' => 2,
            'donor_id' => null,
            'document_type' => 'national_id',
            'document_path' => 'uploads/verification/front.png',
            'document_back_path' => null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/admin/donor-verifications');

        $response->assertOk();
        $response->assertSee('Unlinked submission');
        $response->assertSee('Donor ID unavailable');
        $response->assertSee('Verification #2');
        $response->assertSee('Front: Available');
        $response->assertSee('Back: Not recorded');
    }

    public function test_admin_list_distinguishes_a_recorded_but_missing_file_from_a_submission(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        DB::table('donor_verifications')->insert([
            'verification_id' => 2,
            'donor_id' => 716,
            'document_type' => 'national_id',
            'document_path' => 'uploads/verification/missing-front.png',
            'document_back_path' => null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/admin/donor-verifications');

        $response->assertOk();
        $response->assertSee('Verification #2');
        $response->assertSee('Front: File missing');
        $response->assertSee('optimized preview could not be loaded');
    }

    public function test_unlinked_submission_cannot_be_approved_as_if_it_belonged_to_a_donor(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        DB::table('donor_verifications')->insert([
            'verification_id' => 2,
            'donor_id' => null,
            'document_type' => 'national_id',
            'document_path' => 'uploads/verification/front.png',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['admin_id' => 7])->patch('/admin/donor-verifications/2/approve');

        $response->assertRedirect(route('admin.donor-verifications.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donor_verifications', ['verification_id' => 2, 'status' => 'pending']);
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
