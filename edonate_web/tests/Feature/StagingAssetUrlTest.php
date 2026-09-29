<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class StagingAssetUrlTest extends TestCase
{
    public function test_assets_can_use_the_staging_subdirectory_as_their_origin(): void
    {
        $assetOrigin = 'https://edonate.online/staging';

        config(['app.asset_url' => $assetOrigin]);

        $this->assertSame(
            $assetOrigin.'/build/assets/adminlte.css',
            asset('build/assets/adminlte.css')
        );
        $this->assertSame(
            $assetOrigin.'/vendor/bootstrap/bootstrap.min.css',
            asset('vendor/bootstrap/bootstrap.min.css')
        );

        $adminlteStylesheet = Vite::asset('resources/css/adminlte.css');

        $this->assertStringStartsWith(
            $assetOrigin.'/build/assets/adminlte-',
            $adminlteStylesheet
        );
        $this->assertStringEndsWith('.css', $adminlteStylesheet);
    }
}
