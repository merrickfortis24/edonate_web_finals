<?php

declare(strict_types=1);

const EDONATE_STAGING_ROUTE_BEGIN = '# BEGIN eDonate staging front-controller route';
const EDONATE_STAGING_ROUTE_END = '# END eDonate staging front-controller route';

/**
 * Add or refresh the narrowly scoped staging rewrite without replacing the
 * site's existing production rewrite rules.
 */
function withEdonateStagingParentRoute(string $contents): string
{
    $crlfCount = substr_count($contents, "\r\n");
    $bareLfCount = substr_count($contents, "\n") - $crlfCount;
    $lineEnding = $crlfCount > $bareLfCount ? "\r\n" : "\n";
    $block = implode($lineEnding, [
        '    '.EDONATE_STAGING_ROUTE_BEGIN,
        '    RewriteCond %{REQUEST_URI} ^/staging(?:/|$) [NC]',
        '    RewriteCond %{REQUEST_FILENAME} !-f',
        '    RewriteCond %{REQUEST_FILENAME} !-d',
        '    RewriteRule ^staging(?:/.*)?$ staging/index.php [L,QSA]',
        '    '.EDONATE_STAGING_ROUTE_END,
    ]);

    $beginCount = substr_count($contents, EDONATE_STAGING_ROUTE_BEGIN);
    $endCount = substr_count($contents, EDONATE_STAGING_ROUTE_END);

    if ($beginCount !== $endCount || $beginCount > 1) {
        throw new RuntimeException('Staging rewrite markers are incomplete or duplicated.');
    }

    if ($beginCount === 1) {
        $pattern = '/^[ \t]*'.preg_quote(EDONATE_STAGING_ROUTE_BEGIN, '/').'.*?^[ \t]*'.preg_quote(EDONATE_STAGING_ROUTE_END, '/').'[ \t]*\R?/ms';

        if (preg_match($pattern, $contents, $matches) === 1) {
            $normalizeLineEndings = static fn (string $value): string => str_replace(["\r\n", "\r"], "\n", $value);
            $existingBlock = rtrim($normalizeLineEndings($matches[0]), "\n");

            if ($existingBlock === $normalizeLineEndings($block)) {
                return $contents;
            }
        }

        $updated = preg_replace($pattern, $block.$lineEnding, $contents, 1, $replacements);

        if (! is_string($updated) || $replacements !== 1) {
            throw new RuntimeException('Unable to safely refresh the staging rewrite block.');
        }

        return $updated;
    }

    $anchor = '    # Send Requests To Front Controller...';

    if (substr_count($contents, $anchor) !== 1) {
        throw new RuntimeException('The expected front-controller rewrite anchor was not found exactly once.');
    }

    return str_replace($anchor, $block.$lineEnding.$lineEnding.$anchor, $contents);
}

/** Install the staging-only rule into the shared Hostinger document root. */
function installEdonateStagingParentRoute(): void
{
    $expectedRoot = '/home/u156729731/domains/edonate.online/public_html';
    $stagingRoot = $expectedRoot.'/staging';
    $expectedIndex = $stagingRoot.'/index.php';

    if (is_link($expectedRoot) || realpath($expectedRoot) !== $expectedRoot) {
        throw new RuntimeException('The configured Hostinger document root did not resolve to the expected path.');
    }

    if (is_link($stagingRoot) || realpath($stagingRoot) !== $stagingRoot
        || is_link($expectedIndex) || realpath($expectedIndex) !== $expectedIndex || ! is_file($expectedIndex)) {
        throw new RuntimeException('The staging front controller is missing or resolves outside the staging directory.');
    }

    $path = $expectedRoot.'/.htaccess';

    if (is_link($path) || ! is_file($path)) {
        throw new RuntimeException('The shared document-root .htaccess is missing or is not a regular file.');
    }

    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        throw new RuntimeException('Unable to read the shared document-root .htaccess.');
    }

    $updated = withEdonateStagingParentRoute($contents);

    if ($updated === $contents) {
        echo "Staging URL routing is already installed.\n";

        return;
    }

    $temporaryPath = tempnam($expectedRoot, '.edonate-staging-route-');

    if (! is_string($temporaryPath)) {
        throw new RuntimeException('Unable to create a temporary rewrite file in the document root.');
    }

    try {
        if (realpath(dirname($temporaryPath)) !== $expectedRoot) {
            throw new RuntimeException('The temporary rewrite file was created outside the document root.');
        }

        $permissions = fileperms($path);
        if ($permissions === false || file_put_contents($temporaryPath, $updated, LOCK_EX) === false
            || ! chmod($temporaryPath, $permissions & 0777) || ! rename($temporaryPath, $path)) {
            throw new RuntimeException('Unable to atomically install the staging rewrite.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }

    echo "Installed the staging-only URL rewrite; production routes were left unchanged.\n";
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        installEdonateStagingParentRoute();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Staging URL routing was not changed: '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}
