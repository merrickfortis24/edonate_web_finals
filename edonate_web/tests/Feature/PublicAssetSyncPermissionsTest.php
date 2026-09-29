<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicAssetSyncPermissionsTest extends TestCase
{
    public function test_synced_public_assets_are_readable_even_with_a_restrictive_deploy_umask(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX permission bits are verified on the Linux deployment runner.');
        }

        require_once base_path('scripts/sync-public-assets.php');

        $temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'edonate-public-sync-'.bin2hex(random_bytes(8));
        $source = $temporaryRoot.DIRECTORY_SEPARATOR.'source';
        $destination = $temporaryRoot.DIRECTORY_SEPARATOR.'served';
        $previousUmask = umask(0077);

        try {
            mkdir($source.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'nested', 0755, true);
            file_put_contents(
                $source.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'admin.css',
                'body { color: #222; }'
            );

            \syncDirectory($source, $destination, false);

            $this->assertSame(0755, fileperms($destination) & 0777);
            $this->assertSame(0755, fileperms($destination.DIRECTORY_SEPARATOR.'assets') & 0777);
            $this->assertSame(0755, fileperms($destination.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'nested') & 0777);
            $this->assertSame(
                0644,
                fileperms($destination.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'admin.css') & 0777
            );
            $this->assertSame(
                'body { color: #222; }',
                file_get_contents($destination.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'admin.css')
            );
        } finally {
            umask($previousUmask);
            File::deleteDirectory($temporaryRoot);
        }
    }
}
