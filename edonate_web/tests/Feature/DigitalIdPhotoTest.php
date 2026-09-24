<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalIdPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('donors', function (Blueprint $table): void {
            $table->unsignedBigInteger('donor_id')->primary();
            $table->string('profile_photo_path')->nullable();
        });

        DB::table('donors')->insert(['donor_id' => 1, 'profile_photo_path' => null]);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_admin_uploads_private_photo_and_reads_it_through_the_protected_route(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->withSession(['admin_id' => 5, 'admin_role' => 'admin'])
            ->postJson(route('admin.users.photo.upload', ['donor' => 1]), [
                'photo' => $this->validPngUpload(),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Donor photo updated.')
            ->assertJsonMissingPath('path');

        $storedPath = DB::table('donors')->where('donor_id', 1)->value('profile_photo_path');
        $this->assertIsString($storedPath);
        $this->assertStringStartsWith('donor-profile-photos/1/', $storedPath);
        Storage::disk('local')->assertExists($storedPath);
        Storage::disk('public')->assertMissing($storedPath);

        $response = $this->withSession(['admin_id' => 5, 'admin_role' => 'admin'])
            ->get(route('admin.users.photo', ['donor' => 1]));
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_profile_photo_route_requires_an_admin_session(): void
    {
        $this->get(route('admin.users.photo', ['donor' => 1]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_photo_upload_rejects_oversized_images(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->withSession(['admin_id' => 5, 'admin_role' => 'admin'])
            ->postJson(route('admin.users.photo.upload', ['donor' => 1]), [
                'photo' => $this->validPngUpload('large.png', 2_100_000),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_invalid_stored_photo_path_cannot_escape_private_storage(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        DB::table('donors')->where('donor_id', 1)->update([
            'profile_photo_path' => 'donor-profile-photos/1/../../outside.jpg',
        ]);

        $this->withSession(['admin_id' => 5, 'admin_role' => 'admin'])
            ->get(route('admin.users.photo', ['donor' => 1]))
            ->assertNotFound();
    }

    private function validPngUpload(string $filename = 'donor.png', int $paddingBytes = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/d3sAAAAASUVORK5CYII=', true);
        $this->assertNotFalse($png);

        return UploadedFile::fake()->createWithContent($filename, $png.str_repeat("\0", $paddingBytes));
    }
}
