<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../scripts/install-staging-parent-routing.php';

class StagingParentRoutingTest extends TestCase
{
    public function test_it_routes_only_non_file_staging_requests_to_the_staging_front_controller(): void
    {
        $source = <<<'HTACCESS'
Options -MultiViews -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;

        $updated = withEdonateStagingParentRoute($source);

        self::assertStringContainsString('RewriteCond %{REQUEST_URI} ^/staging(?:/|$) [NC]', $updated);
        self::assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $updated);
        self::assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-d', $updated);
        self::assertStringContainsString('RewriteRule ^staging(?:/.*)?$ staging/index.php [L,QSA]', $updated);
        self::assertLessThan(
            strpos($updated, '# Send Requests To Front Controller...'),
            strpos($updated, 'RewriteRule ^staging(?:/.*)?$ staging/index.php [L,QSA]')
        );
        self::assertSame($updated, withEdonateStagingParentRoute($updated));
    }

    public function test_it_fails_closed_when_the_existing_front_controller_anchor_is_unexpected(): void
    {
        $this->expectException(RuntimeException::class);
        withEdonateStagingParentRoute("RewriteEngine On\nRewriteRule ^ index.php [L]\n");
    }

    public function test_it_rejects_partial_or_duplicate_staging_markers(): void
    {
        $this->expectException(RuntimeException::class);
        withEdonateStagingParentRoute(EDONATE_STAGING_ROUTE_BEGIN."\nrule\n");
    }

    public function test_the_tracked_document_root_rule_is_already_normalized(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $path = dirname($projectRoot).DIRECTORY_SEPARATOR.'public_html'.DIRECTORY_SEPARATOR.'.htaccess';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertSame($contents, withEdonateStagingParentRoute($contents));
    }
}
