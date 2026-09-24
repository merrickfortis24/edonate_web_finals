<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DonorProfilePhotoController extends Controller
{
    private const DIRECTORY = 'donor-profile-photos';

    private const MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function show(int $donor)
    {
        if (! Schema::hasColumn('donors', 'profile_photo_path')) {
            abort(404);
        }

        $donorRecord = Donor::query()->findOrFail($donor);
        $relativePath = $this->validatedStoredPath($donorRecord->profile_photo_path, $donor);
        if ($relativePath === null) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $absolutePath = realpath($disk->path($relativePath));
        if (! $root || ! $absolutePath || ! is_file($absolutePath)
            || ! str_starts_with($absolutePath, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        if (! is_string($mimeType) || ! array_key_exists($mimeType, self::MIME_TYPES)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, int $donor): JsonResponse
    {
        $donorRecord = Donor::query()->findOrFail($donor);
        if (! Schema::hasColumn('donors', 'profile_photo_path')) {
            return response()->json([
                'message' => 'Profile photo storage is not available yet. Run the latest database migrations.',
            ], 409);
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
        ]);

        $upload = $validated['photo'];
        $mimeType = $upload->getMimeType();
        $extension = self::MIME_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw ValidationException::withMessages(['photo' => 'Upload a JPG, PNG, or WebP image.']);
        }

        $disk = Storage::disk('local');
        $filename = Str::random(40).'.'.$extension;
        $relativePath = self::DIRECTORY.'/'.$donor.'/'.$filename;

        try {
            $storedPath = $disk->putFileAs(self::DIRECTORY.'/'.$donor, $upload, $filename);
            if (! $storedPath) {
                throw new \RuntimeException('The uploaded donor photo could not be saved.');
            }

            $oldPath = DB::transaction(function () use ($donorRecord, $relativePath, $donor): ?string {
                $locked = Donor::query()->where('donor_id', $donorRecord->donor_id)->lockForUpdate()->firstOrFail();
                $previousPath = $this->validatedStoredPath($locked->profile_photo_path, $donor);
                $locked->forceFill(['profile_photo_path' => $relativePath])->save();

                return $previousPath;
            });
        } catch (Throwable $exception) {
            $disk->delete($relativePath);
            report($exception);

            return response()->json(['message' => 'Unable to save the donor photo. Please try again.'], 500);
        }

        if ($oldPath !== null && $oldPath !== $relativePath) {
            $disk->delete($oldPath);
        }

        $this->audit($request, $donor, 'donor_profile_photo_updated');

        return response()->json([
            'message' => 'Donor photo updated.',
            'profile_photo_url' => route('admin.users.photo', ['donor' => $donor]).'?v='.now()->timestamp,
        ]);
    }

    private function validatedStoredPath(mixed $path, int $donor): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $prefix = self::DIRECTORY.'/'.$donor.'/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $filename = substr($path, strlen($prefix));
        if (! preg_match('/\A[a-zA-Z0-9]{20,80}\.(?:jpg|jpeg|png|webp)\z/', $filename)) {
            return null;
        }

        return $prefix.$filename;
    }

    private function audit(Request $request, int $donorId, string $action): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin')),
                'actor_role' => (string) $request->session()->get('admin_role', 'admin'),
                'action_type' => $action,
                'module_type' => 'user_management',
                'target_table' => 'donors',
                'target_id' => $donorId,
                'description' => 'Updated the donor profile photo.',
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => json_encode(['donor_id' => $donorId], JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
